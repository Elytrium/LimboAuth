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
import java.text.MessageFormat;
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
import net.elytrium.limboauth.migration.MigrationHash;
import net.elytrium.limboauth.model.RegisteredPlayer;
import net.kyori.adventure.bossbar.BossBar;
import net.kyori.adventure.text.Component;
import net.kyori.adventure.title.Title;
import org.checkerframework.checker.nullness.qual.Nullable;
import org.jooq.DSLContext;
import org.jooq.impl.DSL;

public class AuthSessionHandler implements LimboSessionHandler {

  private static final CodeVerifier TOTP_CODE_VERIFIER = new DefaultCodeVerifier(new DefaultCodeGenerator(), new SystemTimeProvider());
  private static final BCrypt.Verifyer HASH_VERIFIER = BCrypt.verifyer();
  private static final BCrypt.Hasher HASHER = BCrypt.withDefaults();

  private static BossBar.Color bossbarColor;
  private static BossBar.Overlay bossbarOverlay;
  private static Component ipLimitKick;
  private static Component databaseErrorKick;
  private static String wrongNicknameCaseKick;
  private static Component timesUp;
  private static Component registerSuccessful;
  @Nullable
  private static Title registerSuccessfulTitle;
  private static Component[] loginWrongPassword;
  private static Component loginWrongPasswordKick;
  private static Component totp;
  @Nullable
  private static Title totpTitle;
  private static Component register;
  @Nullable
  private static Title registerTitle;
  private static Component[] login;
  @Nullable
  private static Title loginTitle;
  private static Component registerDifferentPasswords;
  private static Component registerPasswordTooLong;
  private static Component registerPasswordTooShort;
  private static Component registerPasswordUnsafe;
  private static Component loginSuccessful;
  private static Component sessionExpired;
  @Nullable
  private static Title loginSuccessfulTitle;
  @Nullable
  private static MigrationHash migrationHash;

  private final DSLContext dslContext;
  private final Player proxyPlayer;
  private final LimboAuth plugin;
  private final Settings settings;

  private final long joinTime = System.currentTimeMillis();
  private final BossBar bossBar = BossBar.bossBar(
      Component.empty(),
      1.0F,
      bossbarColor,
      bossbarOverlay
  );
  private final boolean loginOnlyByMod;

  @Nullable
  private RegisteredPlayer playerInfo;

  private ScheduledFuture<?> authMainTask;

  private LimboPlayer player;
  private int attempts;
  private boolean totpState;
  private String tempPassword;
  private boolean tokenReceived;

  public AuthSessionHandler(DSLContext dslContext, Player proxyPlayer, LimboAuth plugin, Settings settings, @Nullable RegisteredPlayer playerInfo) {
    this.dslContext = dslContext;
    this.proxyPlayer = proxyPlayer;
    this.plugin = plugin;
    this.settings = settings;
    this.playerInfo = playerInfo;
    this.loginOnlyByMod = this.settings.main.mod.enabled && (this.settings.main.mod.loginOnlyByMod || (playerInfo != null && playerInfo.isOnlyByMod()));
    this.attempts = this.settings.main.loginAttempts;
  }

  @Override
  public void onSpawn(Limbo server, LimboPlayer player) {
    this.player = player;

    if (this.settings.main.disableFalling) {
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
                  .ge(System.currentTimeMillis() - this.settings.main.ipLimitValidTime)))
          .fetchAsync()
          .thenAccept(registeredResult -> {
            if (registeredResult.get(0).get(0, Integer.class) >= this.settings.main.ipLimitRegistrations) {
              this.proxyPlayer.disconnect(ipLimitKick);
            } else {
              this.onSpawnAfterChecks();
            }
          })
          .exceptionally(e -> {
            this.plugin.handleSqlError(e);
            this.proxyPlayer.disconnect(databaseErrorKick);
            return null;
          });
    } else {
      if (!this.proxyPlayer.getUsername().equals(this.playerInfo.getNickname())) {
        this.proxyPlayer.disconnect(serializer.deserialize(
            MessageFormat.format(wrongNicknameCaseKick, this.playerInfo.getNickname(), this.proxyPlayer.getUsername()))
        );
      } else {
        this.onSpawnAfterChecks();
      }
    }
  }

  private void onSpawnAfterChecks() {
    Serializer serializer = this.plugin.getSerializer();
    boolean bossBarEnabled = !this.loginOnlyByMod && this.settings.main.enableBossbar;
    int authTime = this.settings.main.authTime;
    float multiplier = 1000.0F / authTime;
    this.authMainTask = this.player.getScheduledExecutor().scheduleWithFixedDelay(() -> {
      if (System.currentTimeMillis() - this.joinTime > authTime) {
        this.proxyPlayer.disconnect(timesUp);
      } else {
        if (bossBarEnabled) {
          float secondsLeft = (authTime - (System.currentTimeMillis() - this.joinTime)) / 1000.0F;
          this.bossBar.name(serializer.deserialize(MessageFormat.format(this.settings.main.strings.BOSSBAR, (int) secondsLeft)));
          // It's possible, that the progress value can overcome 1, e.g. 1.0000001.
          this.bossBar.progress(Math.min(1.0F, secondsLeft * multiplier));
        }
      }
    }, 0, 1, TimeUnit.SECONDS);

    if (bossBarEnabled) {
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

    String[] args = message.split(" ");
    if (args.length != 0 && this.checkArgsLength(args.length)) {
      Command command = Command.parse(this.settings, args[0]);
      if (command == Command.REGISTER && !this.totpState && this.playerInfo == null) {
        String password = args[1];
        if (this.checkPasswordsRepeat(args) && this.checkPasswordLength(password) && this.checkPasswordStrength(password)) {
          this.saveTempPassword(password);
          RegisteredPlayer registeredPlayer = new RegisteredPlayer(this.proxyPlayer).setPassword(this.settings, password);

          this.dslContext.insertInto(RegisteredPlayer.Table.INSTANCE)
              .values(registeredPlayer)
              .executeAsync();
          this.playerInfo = registeredPlayer;

          this.proxyPlayer.sendMessage(registerSuccessful);
          if (registerSuccessfulTitle != null) {
            this.proxyPlayer.showTitle(registerSuccessfulTitle);
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

        if (!password.isEmpty() && checkPassword(this.settings, this.proxyPlayer.getUsername(), this.playerInfo.getHash(), password, this.dslContext)) {
          if (this.playerInfo.getTotpToken().isEmpty()) {
            this.finishLogin();
          } else {
            this.totpState = true;
            this.sendMessage(true);
          }
        } else if (--this.attempts != 0) {
          this.proxyPlayer.sendMessage(loginWrongPassword[this.attempts - 1]);
          this.checkBruteforceAttempts();
        } else {
          this.proxyPlayer.disconnect(loginWrongPasswordKick);
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
    if (this.settings.main.mod.enabled && packet instanceof PluginMessage) {
      PluginMessage pluginMessage = (PluginMessage) packet;
      String channel = pluginMessage.getChannel();

      if (channel.equals("MC|Brand") || channel.equals("minecraft:brand")) {
        // Minecraft can't handle the plugin message immediately after going to the PLAY
        // state, so we have to postpone sending it
        if (this.settings.main.mod.enabled) {
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
          this.proxyPlayer.sendMessage(sessionExpired);
          return;
        }

        long issueTime = data.readLong();
        long hash = data.readLong();

        if (this.playerInfo.getTokenIssuedAt() > issueTime) {
          this.proxyPlayer.sendMessage(sessionExpired);
          return;
        }

        byte[] lowercaseNicknameSerialized = this.playerInfo.getLowercaseNickname().getBytes(StandardCharsets.UTF_8);
        long correctHash = SipHasher.init(this.settings.main.mod.verifyKey)
            .update(lowercaseNicknameSerialized)
            .update(Longs.toByteArray(issueTime))
            .digest();

        if (hash != correctHash) {
          this.checkBruteforceAttempts();
          this.proxyPlayer.sendMessage(sessionExpired);
          return;
        }

        this.finishAuth();
      }
    }
  }

  private void checkBruteforceAttempts() {
    this.plugin.incrementBruteforceAttempts(this.proxyPlayer.getRemoteAddress().getAddress());
    if (this.plugin.getBruteforceAttempts(this.proxyPlayer.getRemoteAddress().getAddress()) >= this.settings.main.bruteforceMaxAttempts) {
      this.proxyPlayer.disconnect(loginWrongPasswordKick);
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

    this.proxyPlayer.hideBossBar(this.bossBar);
  }

  private void sendMessage(boolean sendTitle) {
    if (this.totpState) {
      this.proxyPlayer.sendMessage(totp);
      if (sendTitle && totpTitle != null) {
        this.proxyPlayer.showTitle(totpTitle);
      }
    } else if (this.playerInfo == null) {
      this.proxyPlayer.sendMessage(register);
      if (sendTitle && registerTitle != null) {
        this.proxyPlayer.showTitle(registerTitle);
      }
    } else {
      this.proxyPlayer.sendMessage(login[this.attempts - 1]);
      if (sendTitle && loginTitle != null) {
        this.proxyPlayer.showTitle(loginTitle);
      }
    }
  }

  private boolean checkArgsLength(int argsLength) {
    if (this.playerInfo == null && this.settings.main.registerNeedRepeatPassword) {
      return argsLength == 3;
    } else {
      return argsLength == 2;
    }
  }

  private boolean checkPasswordsRepeat(String[] args) {
    if (!this.settings.main.registerNeedRepeatPassword || args[1].equals(args[2])) {
      return true;
    } else {
      this.proxyPlayer.sendMessage(registerDifferentPasswords);
      return false;
    }
  }

  private boolean checkPasswordLength(String password) {
    int length = password.length();
    if (length > this.settings.main.maxPasswordLength) {
      this.proxyPlayer.sendMessage(registerPasswordTooLong);
      return false;
    } else if (length < this.settings.main.minPasswordLength) {
      this.proxyPlayer.sendMessage(registerPasswordTooShort);
      return false;
    } else {
      return true;
    }
  }

  private boolean checkPasswordStrength(String password) {
    if (this.settings.main.checkPasswordStrength && this.plugin.getUnsafePasswords().contains(password)) {
      this.proxyPlayer.sendMessage(registerPasswordUnsafe);
      return false;
    } else {
      return true;
    }
  }

  private void finishLogin() {
    this.proxyPlayer.sendMessage(loginSuccessful);
    if (loginSuccessfulTitle != null) {
      this.proxyPlayer.showTitle(loginSuccessfulTitle);
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
    if (this.settings.main.crackedTitleSettings.clearAfterLogin) {
      this.proxyPlayer.clearTitle();
    }

    this.plugin.updateLoginData(this.proxyPlayer);
    this.plugin.cacheAuthUser(this.proxyPlayer);
    this.player.disconnect();
  }

  public static void reload(Serializer serializer, Settings settings) {
    bossbarColor = settings.main.bossbarColor;
    bossbarOverlay = settings.main.bossbarOverlay;
    ipLimitKick = serializer.deserialize(settings.main.strings.IP_LIMIT_KICK);
    databaseErrorKick = serializer.deserialize(settings.main.strings.DATABASE_ERROR_KICK);
    wrongNicknameCaseKick = settings.main.strings.WRONG_NICKNAME_CASE_KICK;
    timesUp = serializer.deserialize(settings.main.strings.TIMES_UP);
    registerSuccessful = serializer.deserialize(settings.main.strings.REGISTER_SUCCESSFUL);
    if (settings.main.strings.REGISTER_SUCCESSFUL_TITLE.isEmpty() && settings.main.strings.REGISTER_SUCCESSFUL_SUBTITLE.isEmpty()) {
      registerSuccessfulTitle = null;
    } else {
      registerSuccessfulTitle = Title.title(
          serializer.deserialize(settings.main.strings.REGISTER_SUCCESSFUL_TITLE),
          serializer.deserialize(settings.main.strings.REGISTER_SUCCESSFUL_SUBTITLE),
          settings.main.crackedTitleSettings.toTimes()
      );
    }
    int loginAttempts = settings.main.loginAttempts;
    loginWrongPassword = new Component[loginAttempts];
    for (int i = 0; i < loginAttempts; ++i) {
      loginWrongPassword[i] = serializer.deserialize(MessageFormat.format(settings.main.strings.LOGIN_WRONG_PASSWORD, i + 1));
    }
    loginWrongPasswordKick = serializer.deserialize(settings.main.strings.LOGIN_WRONG_PASSWORD_KICK);
    totp = serializer.deserialize(settings.main.strings.TOTP);
    if (settings.main.strings.TOTP_TITLE.isEmpty() && settings.main.strings.TOTP_SUBTITLE.isEmpty()) {
      totpTitle = null;
    } else {
      totpTitle = Title.title(
          serializer.deserialize(settings.main.strings.TOTP_TITLE),
          serializer.deserialize(settings.main.strings.TOTP_SUBTITLE),
          settings.main.crackedTitleSettings.toTimes()
      );
    }
    register = serializer.deserialize(settings.main.strings.REGISTER);
    if (settings.main.strings.REGISTER_TITLE.isEmpty() && settings.main.strings.REGISTER_SUBTITLE.isEmpty()) {
      registerTitle = null;
    } else {
      registerTitle = Title.title(
          serializer.deserialize(settings.main.strings.REGISTER_TITLE),
          serializer.deserialize(settings.main.strings.REGISTER_SUBTITLE),
          settings.main.crackedTitleSettings.toTimes()
      );
    }
    login = new Component[loginAttempts];
    for (int i = 0; i < loginAttempts; ++i) {
      login[i] = serializer.deserialize(MessageFormat.format(settings.main.strings.LOGIN, i + 1));
    }
    if (settings.main.strings.LOGIN_TITLE.isEmpty() && settings.main.strings.LOGIN_SUBTITLE.isEmpty()) {
      loginTitle = null;
    } else {
      loginTitle = Title.title(
          serializer.deserialize(MessageFormat.format(settings.main.strings.LOGIN_TITLE, loginAttempts)),
          serializer.deserialize(MessageFormat.format(settings.main.strings.LOGIN_SUBTITLE, loginAttempts)),
          settings.main.crackedTitleSettings.toTimes()
      );
    }
    registerDifferentPasswords = serializer.deserialize(settings.main.strings.REGISTER_DIFFERENT_PASSWORDS);
    registerPasswordTooLong = serializer.deserialize(settings.main.strings.REGISTER_PASSWORD_TOO_LONG);
    registerPasswordTooShort = serializer.deserialize(settings.main.strings.REGISTER_PASSWORD_TOO_SHORT);
    registerPasswordUnsafe = serializer.deserialize(settings.main.strings.REGISTER_PASSWORD_UNSAFE);
    loginSuccessful = serializer.deserialize(settings.main.strings.LOGIN_SUCCESSFUL);
    sessionExpired = serializer.deserialize(settings.main.strings.MOD_SESSION_EXPIRED);
    if (settings.main.strings.LOGIN_SUCCESSFUL_TITLE.isEmpty() && settings.main.strings.LOGIN_SUCCESSFUL_SUBTITLE.isEmpty()) {
      loginSuccessfulTitle = null;
    } else {
      loginSuccessfulTitle = Title.title(
          serializer.deserialize(settings.main.strings.LOGIN_SUCCESSFUL_TITLE),
          serializer.deserialize(settings.main.strings.LOGIN_SUCCESSFUL_SUBTITLE),
          settings.main.crackedTitleSettings.toTimes()
      );
    }

    migrationHash = settings.main.migrationHash;
  }

  public static boolean checkPassword(Settings settings, String lowercaseNickname, String hash, String password, DSLContext dslContext) {
    boolean isCorrect = HASH_VERIFIER.verify(
        password.getBytes(StandardCharsets.UTF_8),
        hash.replace("BCRYPT$", "$2a$").getBytes(StandardCharsets.UTF_8)
    ).verified;

    if (!isCorrect && migrationHash != null) {
      isCorrect = migrationHash.checkPassword(hash, password);
      if (isCorrect) {
        dslContext.update(RegisteredPlayer.Table.INSTANCE)
            .set(RegisteredPlayer.Table.HASH_FIELD, RegisteredPlayer.genHash(settings, password))
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

    static Command parse(Settings settings, String command) {
      if (settings.main.registerCommand.contains(command)) {
        return Command.REGISTER;
      } else if (settings.main.loginCommand.contains(command)) {
        return Command.LOGIN;
      } else if (settings.main.totpCommand.contains(command)) {
        return Command.TOTP;
      } else {
        return Command.INVALID;
      }
    }
  }
}
