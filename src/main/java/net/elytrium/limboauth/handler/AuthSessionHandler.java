/*
 * Copyright (C) 2021 - 2023 Elytrium
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

package net.elytrium.limboauth.handler;

import at.favre.lib.crypto.bcrypt.BCrypt;
import com.google.common.primitives.Longs;
import com.velocitypowered.api.proxy.Player;
import com.velocitypowered.proxy.protocol.packet.PluginMessage;
import dev.samstevens.totp.code.CodeVerifier;
import dev.samstevens.totp.code.DefaultCodeGenerator;
import dev.samstevens.totp.code.DefaultCodeVerifier;
import dev.samstevens.totp.time.SystemTimeProvider;
import io.netty.buffer.ByteBuf;
import io.whitfin.siphash.SipHasher;
import java.nio.charset.StandardCharsets;
import java.util.concurrent.ScheduledFuture;
import java.util.concurrent.TimeUnit;
import net.elytrium.commons.kyori.serialization.Serializer;
import net.elytrium.limboapi.api.Limbo;
import net.elytrium.limboapi.api.LimboSessionHandler;
import net.elytrium.limboapi.api.player.LimboPlayer;
import net.elytrium.limboauth.LimboAuth;
import net.elytrium.limboauth.Settings;
import net.elytrium.limboauth.event.PostAuthorizationEvent;
import net.elytrium.limboauth.event.PostRegisterEvent;
import net.elytrium.limboauth.event.TaskEvent;
import net.elytrium.limboauth.model.RegisteredPlayer;
import net.elytrium.serializer.placeholders.Placeholders;
import net.kyori.adventure.bossbar.BossBar;
import net.kyori.adventure.text.Component;
import org.checkerframework.checker.nullness.qual.Nullable;
import org.jooq.DSLContext;
import org.jooq.impl.DSL;

public class AuthSessionHandler implements LimboSessionHandler {

  private static final CodeVerifier TOTP_CODE_VERIFIER = new DefaultCodeVerifier(new DefaultCodeGenerator(), new SystemTimeProvider());
  private static final BCrypt.Verifyer HASH_VERIFIER = BCrypt.verifyer(); // TODO find another libraries, compare them and choose better one
  //private static final BCrypt.Hasher HASHER = BCrypt.withDefaults(); // TODO почему он не юзается?

  private final DSLContext dslContext;
  private final Player proxyPlayer;
  private final LimboAuth plugin;

  private final long joinTime = System.currentTimeMillis();
  private final boolean loginOnlyByMod;

  private BossBar bossBar;

  @Nullable
  private RegisteredPlayer playerInfo; // TODO expiring cache

  private ScheduledFuture<?> authMainTask;

  private LimboPlayer player;
  private int attempts;
  private boolean totpState;
  private String tempPassword;
  private boolean tokenReceived;

  public AuthSessionHandler(DSLContext dslContext, Player proxyPlayer, LimboAuth plugin, @Nullable RegisteredPlayer playerInfo) {
    this.dslContext = dslContext;
    this.proxyPlayer = proxyPlayer;
    this.plugin = plugin;
    this.playerInfo = playerInfo;
    this.loginOnlyByMod = Settings.IMP.mod.enabled && (Settings.IMP.mod.loginOnlyByMod || (playerInfo != null && playerInfo.isOnlyByMod()));
    this.attempts = Settings.IMP.loginAttempts;
  }

  @Override
  public void onSpawn(Limbo server, LimboPlayer player) {
    this.player = player;

    if (Settings.IMP.disableFalling) {
      this.player.disableFalling();
    } else {
      this.player.enableFalling();
    }

    Serializer serializer = this.plugin.getSerializer();

    if (this.playerInfo == null) {
      String ip = this.proxyPlayer.getRemoteAddress().getAddress().getHostAddress();
      this.dslContext.selectCount()
          .from(RegisteredPlayer.Table.INSTANCE)
          .where(DSL.field(RegisteredPlayer.Table.IP_FIELD).eq(ip)
              .and(DSL.field(RegisteredPlayer.Table.REG_DATE_FIELD)
                  .ge(System.currentTimeMillis() - Settings.IMP.ipLimitValidTime)))
          .fetchAsync()
          .thenAccept(registeredResult -> {
            if (registeredResult.get(0).value1() >= Settings.IMP.ipLimitRegistrations) {
              this.proxyPlayer.disconnect(Settings.MESSAGES.ipLimitKick);
            } else {
              this.onSpawnAfterChecks();
            }
          })
          .exceptionally(e -> {
            this.plugin.handleSqlError(e);
            this.proxyPlayer.disconnect(Settings.MESSAGES.databaseErrorKick);
            return null;
          });
    } else {
      if (!this.proxyPlayer.getUsername().equals(this.playerInfo.getNickname())) {
        this.proxyPlayer.disconnect(serializer.deserialize(
            Placeholders.replace(Settings.MESSAGES.wrongNicknameCaseKick, this.playerInfo.getNickname(), this.proxyPlayer.getUsername()))
        );
      } else {
        this.onSpawnAfterChecks();
      }
    }
  }

  private void onSpawnAfterChecks() {
    boolean bossBarEnabled = !this.loginOnlyByMod && Settings.IMP.enableBossbar;
    int authTime = Settings.IMP.authTime;
    float multiplier = 1000.0F / authTime;
    this.authMainTask = this.player.getScheduledExecutor().scheduleWithFixedDelay(() -> {
      if (System.currentTimeMillis() - this.joinTime > authTime) {
        this.proxyPlayer.disconnect(Settings.MESSAGES.timesUp);
      } else if (bossBarEnabled) {
        float secondsLeft = (authTime - (System.currentTimeMillis() - this.joinTime)) / 1000.0F;
        this.bossBar.name(Placeholders.replaceFor(Settings.MESSAGES.bossbar, Settings.MESSAGES.bossbar.name(), (int) secondsLeft));
        // It's possible, that the progress value can overcome 1, e.g. 1.0000001.
        this.bossBar.progress(Math.min(1.0F, secondsLeft * multiplier));
      }
    }, 0, 1, TimeUnit.SECONDS);

    if (bossBarEnabled) {
      this.bossBar = BossBar.bossBar(Settings.MESSAGES.bossbar.name(), BossBar.MAX_PROGRESS, Settings.MESSAGES.bossbar.color(), Settings.MESSAGES.bossbar.overlay());
      this.proxyPlayer.showBossBar(this.bossBar);
    }

    if (!this.loginOnlyByMod) {
      this.sendMessage(true);
    }
  }

  @Override
  public void onChat(String message) {
    if (this.loginOnlyByMod) {
      return;
    }

    // TODO stop accepting new messages if already had one command
    String[] args = message.split(" ");
    if (args.length != 0 && this.checkArgsLength(args.length)) {
      Command command = Command.parse(args[0]);
      if (command == Command.REGISTER && !this.totpState && this.playerInfo == null) {
        String password = args[1];
        if (this.checkPasswordsRepeat(args) && this.checkPasswordLength(password) && this.checkPasswordStrength(password)) {
          this.saveTempPassword(password);
          RegisteredPlayer registeredPlayer = new RegisteredPlayer(this.proxyPlayer).setPassword(password);

          this.dslContext.insertInto(RegisteredPlayer.Table.INSTANCE)
              .values(registeredPlayer)
              .executeAsync();
          this.playerInfo = registeredPlayer;

          this.proxyPlayer.sendMessage(Settings.MESSAGES.registerSuccessful);
          if (Settings.MESSAGES.registerSuccessfulTitle != null) {
            this.proxyPlayer.showTitle(Settings.MESSAGES.registerSuccessfulTitle);
          }

          this.plugin.getServer().getEventManager()
              .fire(new PostRegisterEvent(this::finishAuth, this.player, this.playerInfo, this.tempPassword))
              .thenAcceptAsync(this::finishAuth);
        }

        // {@code return} placed here (not above), because
        // AuthSessionHandler#checkPasswordsRepeat, AuthSessionHandler#checkPasswordLength, and AuthSessionHandler#checkPasswordStrength methods are
        // invoking Player#sendMessage that sends its own message in case if the return value is false.
        // If we don't place {@code return} here, another message (AuthSessionHandler#sendMessage) will be sent.
        return;
      } else if (command == Command.LOGIN && !this.totpState && this.playerInfo != null) {
        String password = args[1];
        this.saveTempPassword(password);

        if (!password.isEmpty() && checkPassword(this.proxyPlayer.getUsername(), this.playerInfo.getHash(), password, this.dslContext)) {
          if (this.playerInfo.getTotpToken().isEmpty()) {
            this.finishLogin();
          } else {
            this.totpState = true;
            this.sendMessage(true);
          }
        } else if (--this.attempts != 0) {
          this.proxyPlayer.sendMessage(Placeholders.replace(Settings.MESSAGES.loginWrongPassword, this.attempts));
          this.checkBruteforceAttempts();
        } else {
          this.proxyPlayer.disconnect(Settings.MESSAGES.loginWrongPasswordKick);
        }

        return;
      } else if (command == Command.TOTP && this.totpState && this.playerInfo != null) {
        if (TOTP_CODE_VERIFIER.isValidCode(this.playerInfo.getTotpToken(), args[1])) {
          this.finishLogin();
          return;
        } else {
          this.checkBruteforceAttempts();
        }
      }
    }

    this.sendMessage(false);
  }

  @Override
  public void onGeneric(Object packet) {
    if (Settings.IMP.mod.enabled && packet instanceof PluginMessage pluginMessage) {
      String channel = pluginMessage.getChannel();

      if (channel.equals("MC|Brand") || channel.equals("minecraft:brand")) {
        // Minecraft can't handle the plugin message immediately after going to the PLAY
        // state, so we have to postpone sending it
        if (Settings.IMP.mod.enabled) {
          this.proxyPlayer.sendPluginMessage(this.plugin.getChannelIdentifier(this.proxyPlayer), new byte[0]);
        }
      } else if (channel.equals(this.plugin.getChannelIdentifier(this.proxyPlayer).getId())) {
        if (this.tokenReceived) {
          this.checkBruteforceAttempts();
          this.proxyPlayer.disconnect(Component.empty());
          return;
        }

        this.tokenReceived = true;

        if (this.playerInfo == null) {
          return;
        }

        ByteBuf data = pluginMessage.content();

        if (data.readableBytes() < 16) {
          this.checkBruteforceAttempts();
          this.proxyPlayer.sendMessage(Settings.MESSAGES.modSessionExpired);
          return;
        }

        long issueTime = data.readLong();
        long hash = data.readLong();

        if (this.playerInfo.getTokenIssuedAt() > issueTime) {
          this.proxyPlayer.sendMessage(Settings.MESSAGES.modSessionExpired);
          return;
        }

        byte[] lowercaseNicknameSerialized = this.playerInfo.getLowercaseNickname().getBytes(StandardCharsets.UTF_8);
        long correctHash = SipHasher.init(Settings.IMP.mod.verifyKey)
            .update(lowercaseNicknameSerialized)
            .update(Longs.toByteArray(issueTime))
            .digest();

        if (hash != correctHash) {
          this.checkBruteforceAttempts();
          this.proxyPlayer.sendMessage(Settings.MESSAGES.modSessionExpired);
          return;
        }

        this.finishAuth();
      }
    }
  }

  private void checkBruteforceAttempts() {
    this.plugin.incrementBruteforceAttempts(this.proxyPlayer.getRemoteAddress().getAddress());
    if (this.plugin.getBruteforceAttempts(this.proxyPlayer.getRemoteAddress().getAddress()) >= Settings.IMP.bruteforceMaxAttempts) {
      this.proxyPlayer.disconnect(Settings.MESSAGES.loginWrongPasswordKick);
    }
  }

  private void saveTempPassword(String password) {
    this.tempPassword = password;
  }

  @Override
  public void onDisconnect() {
    if (this.authMainTask != null) {
      this.authMainTask.cancel(true);
    }

    if (this.bossBar != null) {
      this.proxyPlayer.hideBossBar(this.bossBar);
    }
  }

  private void sendMessage(boolean sendTitle) {
    if (this.totpState) {
      this.proxyPlayer.sendMessage(Settings.MESSAGES.totpMessage);
      if (sendTitle && Settings.MESSAGES.totpTitle != null) {
        this.proxyPlayer.showTitle(Settings.MESSAGES.totpTitle);
      }
    } else if (this.playerInfo == null) {
      this.proxyPlayer.sendMessage(Settings.MESSAGES.registerMessage);
      if (sendTitle && Settings.MESSAGES.registerTitle != null) {
        this.proxyPlayer.showTitle(Settings.MESSAGES.registerTitle);
      }
    } else {
      this.proxyPlayer.sendMessage(Placeholders.replace(Settings.MESSAGES.loginMessage, this.attempts));
      if (sendTitle && Settings.MESSAGES.loginTitle != null) {
        this.proxyPlayer.showTitle(Settings.MESSAGES.loginTitle);
      }
    }
  }

  private boolean checkArgsLength(int argsLength) {
    return this.playerInfo == null && Settings.IMP.registerNeedRepeatPassword ? argsLength == 3 : argsLength == 2;
  }

  private boolean checkPasswordsRepeat(String[] args) {
    if (!Settings.IMP.registerNeedRepeatPassword || args[1].equals(args[2])) {
      return true;
    } else {
      this.proxyPlayer.sendMessage(Settings.MESSAGES.registerDifferentPasswords);
      return false;
    }
  }

  private boolean checkPasswordLength(String password) {
    int length = password.length();
    if (length > Settings.IMP.maxPasswordLength) {
      this.proxyPlayer.sendMessage(Settings.MESSAGES.registerPasswordTooLong);
      return false;
    } else if (length < Settings.IMP.minPasswordLength) {
      this.proxyPlayer.sendMessage(Settings.MESSAGES.registerPasswordTooShort);
      return false;
    } else {
      return true;
    }
  }

  private boolean checkPasswordStrength(String password) {
    if (Settings.IMP.checkPasswordStrength && this.plugin.getUnsafePasswords().contains(password)) {
      this.proxyPlayer.sendMessage(Settings.MESSAGES.registerPasswordUnsafe);
      return false;
    } else {
      return true;
    }
  }

  private void finishLogin() {
    this.proxyPlayer.sendMessage(Settings.MESSAGES.loginSuccessful);
    if (Settings.MESSAGES.loginSuccessfulTitle != null) {
      this.proxyPlayer.showTitle(Settings.MESSAGES.loginSuccessfulTitle);
    }

    this.plugin.clearBruteforceAttempts(this.proxyPlayer.getRemoteAddress().getAddress());

    this.plugin.getServer().getEventManager()
        .fire(new PostAuthorizationEvent(this::finishAuth, this.player, this.playerInfo, this.tempPassword))
        .thenAcceptAsync(this::finishAuth);
  }

  private void finishAuth(TaskEvent event) {
    if (event.getResult() == TaskEvent.Result.CANCEL) {
      this.proxyPlayer.disconnect(event.getReason());
      return;
    } else if (event.getResult() == TaskEvent.Result.WAIT) {
      return;
    }

    this.finishAuth();
  }

  private void finishAuth() {
    if (Settings.IMP.crackedTitleSettings.clearAfterLogin) {
      this.proxyPlayer.clearTitle();
    }

    this.plugin.updateLoginData(this.proxyPlayer);
    this.plugin.cacheAuthUser(this.proxyPlayer);
    this.player.disconnect();
  }

  public static boolean checkPassword(String lowercaseNickname, String hash, String password, DSLContext dslContext) {
    boolean isCorrect = HASH_VERIFIER.verify(
        password.getBytes(StandardCharsets.UTF_8),
        hash.replace("BCRYPT$", "$2a$").getBytes(StandardCharsets.UTF_8)
    ).verified;

    if (!isCorrect && Settings.IMP.migrationHash != null) {
      isCorrect = Settings.IMP.migrationHash.checkPassword(hash, password);
      if (isCorrect) {
        dslContext.update(RegisteredPlayer.Table.INSTANCE)
            .set(RegisteredPlayer.Table.HASH_FIELD, RegisteredPlayer.genHash(password))
            .set(RegisteredPlayer.Table.TOKEN_ISSUED_AT_FIELD, System.currentTimeMillis())
            .where(DSL.field(RegisteredPlayer.Table.LOWERCASE_NICKNAME_FIELD).eq(lowercaseNickname))
            .executeAsync();
      }
    }

    return isCorrect;
  }

  public static CodeVerifier getTotpCodeVerifier() {
    return TOTP_CODE_VERIFIER;
  }

  private enum Command {

    INVALID,
    REGISTER,
    LOGIN,
    TOTP;

    static Command parse(String command) {
      return Settings.IMP.registerCommand.contains(command) ? Command.REGISTER
          : Settings.IMP.loginCommand.contains(command) ? Command.LOGIN
          : Settings.IMP.totpCommand.contains(command) ? Command.TOTP
          : Command.INVALID;
    }
  }
}
