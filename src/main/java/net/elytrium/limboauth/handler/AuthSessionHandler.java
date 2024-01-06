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
import net.elytrium.limboauth.model.SQLRuntimeException;
import net.elytrium.limboauth.storage.PlayerStorage;
import net.elytrium.limboauth.util.CryptUtils;
import net.kyori.adventure.bossbar.BossBar;
import net.kyori.adventure.text.Component;
import net.kyori.adventure.title.Title;
import org.checkerframework.checker.nullness.qual.Nullable;

import java.nio.charset.StandardCharsets;
import java.sql.SQLException;
import java.text.MessageFormat;
import java.util.concurrent.ScheduledFuture;
import java.util.concurrent.TimeUnit;

public class AuthSessionHandler implements LimboSessionHandler {

  private static final CodeVerifier TOTP_CODE_VERIFIER = new DefaultCodeVerifier(new DefaultCodeGenerator(), new SystemTimeProvider());
  private static final BCrypt.Hasher HASHER = BCrypt.withDefaults();

  private static Component ratelimited;
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

  private final PlayerStorage playerStorage;
  private final Player proxyPlayer;
  private final LimboAuth plugin;

  private final long joinTime = System.currentTimeMillis();
  private final BossBar bossBar = BossBar.bossBar(
      Component.empty(),
      1.0F,
      bossbarColor,
      bossbarOverlay
  );
  private final boolean loginOnlyByMod = Settings.IMP.MAIN.MOD.ENABLED && Settings.IMP.MAIN.MOD.LOGIN_ONLY_BY_MOD;

  @Nullable
  private RegisteredPlayer playerInfo;

  private ScheduledFuture<?> authMainTask;

  private LimboPlayer player;
  private int attempts = Settings.IMP.MAIN.LOGIN_ATTEMPTS;
  private boolean totpState;
  private String tempPassword;
  private boolean tokenReceived;

  public AuthSessionHandler(PlayerStorage playerStorage, Player proxyPlayer, LimboAuth plugin, @Nullable RegisteredPlayer playerInfo) {
    this.playerStorage = playerStorage;
    this.proxyPlayer = proxyPlayer;
    this.plugin = plugin;
    this.playerInfo = playerInfo;
  }

  @Override
  public void onSpawn(Limbo server, LimboPlayer player) {
    this.player = player;

    if (Settings.IMP.MAIN.DISABLE_FALLING) {
      this.player.disableFalling();
    } else {
      this.player.enableFalling();
    }

    Serializer serializer = LimboAuth.getSerializer();

    if (this.playerInfo == null) {
        playerStorage.getAccountCount(this.proxyPlayer.getRemoteAddress().getAddress()).thenAccept(accountCount -> {
            if (accountCount >= Settings.IMP.MAIN.IP_LIMIT_REGISTRATIONS) {
                this.proxyPlayer.disconnect(ipLimitKick);
            }
        }).orTimeout(2, TimeUnit.SECONDS).exceptionally(throwable -> {
            this.proxyPlayer.disconnect(databaseErrorKick);
            throwable.printStackTrace();
            return null;
        });

        return;
    } else {
      if (!this.proxyPlayer.getUsername().equals(this.playerInfo.getNickname())) {
        this.proxyPlayer.disconnect(serializer.deserialize(
            MessageFormat.format(wrongNicknameCaseKick, this.playerInfo.getNickname(), this.proxyPlayer.getUsername()))
        );
        return;
      }
    }

    boolean bossBarEnabled = !this.loginOnlyByMod && Settings.IMP.MAIN.ENABLE_BOSSBAR;
    int authTime = Settings.IMP.MAIN.AUTH_TIME;
    float multiplier = 1000.0F / authTime;
    this.authMainTask = this.player.getScheduledExecutor().scheduleWithFixedDelay(() -> {
      if (System.currentTimeMillis() - this.joinTime > authTime) {
        this.proxyPlayer.disconnect(timesUp);
      } else {
        if (bossBarEnabled) {
          float secondsLeft = (authTime - (System.currentTimeMillis() - this.joinTime)) / 1000.0F;
          this.bossBar.name(serializer.deserialize(MessageFormat.format(Settings.IMP.MAIN.STRINGS.BOSSBAR, (int) secondsLeft)));
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

    if (!LimboAuth.RATELIMITER.attempt(this.proxyPlayer.getRemoteAddress().getAddress())) {
      this.proxyPlayer.sendMessage(AuthSessionHandler.ratelimited);
      return;
    }

    String[] args = message.split(" ");
    if (args.length != 0 && this.checkArgsLength(args.length)) {
      Command command = Command.parse(args[0]);
      if (command == Command.REGISTER && !this.totpState && this.playerInfo == null) {
        String password = args[1];
        if (this.checkPasswordsRepeat(args) && this.checkPasswordLength(password) && this.checkPasswordStrength(password)) {
          this.saveTempPassword(password);

            PlayerStorage.LoginRegisterResult result = playerStorage.loginOrRegister(
                    this.proxyPlayer.getUsername(),
                    this.proxyPlayer.getUniqueId().toString(),
                    this.proxyPlayer.getRemoteAddress().getAddress().getHostAddress(),
                    password
            );

            if(result != PlayerStorage.LoginRegisterResult.REGISTERED) {
                this.proxyPlayer.disconnect(databaseErrorKick);
                return;
            }

            this.playerInfo = playerStorage.getAccount(this.proxyPlayer.getUsername());

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
        // If we don't place {@code return} here, an another message (AuthSessionHandler#sendMessage) will be sent.
        return;
      } else if (command == Command.LOGIN && !this.totpState && this.playerInfo != null) {
        String password = args[1];
        this.saveTempPassword(password);

        if (password.length() > 0 && CryptUtils.checkPassword(password, this.playerInfo)) {
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
    if (Settings.IMP.MAIN.MOD.ENABLED && packet instanceof PluginMessage) {
      PluginMessage pluginMessage = (PluginMessage) packet;
      String channel = pluginMessage.getChannel();

      if (channel.equals("MC|Brand") || channel.equals("minecraft:brand")) {
        // Minecraft can't handle the plugin message immediately after going to the PLAY
        // state, so we have to postpone sending it
        if (Settings.IMP.MAIN.MOD.ENABLED) {
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
        long correctHash = SipHasher.init(Settings.IMP.MAIN.MOD.VERIFY_KEY)
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
    if (this.plugin.getBruteforceAttempts(this.proxyPlayer.getRemoteAddress().getAddress()) >= Settings.IMP.MAIN.BRUTEFORCE_MAX_ATTEMPTS) {
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
    if (this.playerInfo == null && Settings.IMP.MAIN.REGISTER_NEED_REPEAT_PASSWORD) {
      return argsLength == 3;
    } else {
      return argsLength == 2;
    }
  }

  private boolean checkPasswordsRepeat(String[] args) {
    if (!Settings.IMP.MAIN.REGISTER_NEED_REPEAT_PASSWORD || args[1].equals(args[2])) {
      return true;
    } else {
      this.proxyPlayer.sendMessage(registerDifferentPasswords);
      return false;
    }
  }

  private boolean checkPasswordLength(String password) {
    int length = password.length();
    if (length > Settings.IMP.MAIN.MAX_PASSWORD_LENGTH) {
      this.proxyPlayer.sendMessage(registerPasswordTooLong);
      return false;
    } else if (length < Settings.IMP.MAIN.MIN_PASSWORD_LENGTH) {
      this.proxyPlayer.sendMessage(registerPasswordTooShort);
      return false;
    } else {
      return true;
    }
  }

  private boolean checkPasswordStrength(String password) {
    if (Settings.IMP.MAIN.CHECK_PASSWORD_STRENGTH && this.plugin.getUnsafePasswords().contains(password)) {
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
    if (Settings.IMP.MAIN.CRACKED_TITLE_SETTINGS.CLEAR_AFTER_LOGIN) {
      this.proxyPlayer.clearTitle();
    }

    try {
      this.plugin.updateLoginData(this.proxyPlayer);
    } catch (SQLException e) {
      throw new SQLRuntimeException(e);
    } catch (Throwable e) {
      e.printStackTrace();
    }

    this.player.disconnect();
  }

  public static void reload() {
    Serializer serializer = LimboAuth.getSerializer();
    AuthSessionHandler.ratelimited = serializer.deserialize(Settings.IMP.MAIN.STRINGS.RATELIMITED);
    bossbarColor = Settings.IMP.MAIN.BOSSBAR_COLOR;
    bossbarOverlay = Settings.IMP.MAIN.BOSSBAR_OVERLAY;
    ipLimitKick = serializer.deserialize(Settings.IMP.MAIN.STRINGS.IP_LIMIT_KICK);
    databaseErrorKick = serializer.deserialize(Settings.IMP.MAIN.STRINGS.DATABASE_ERROR_KICK);
    wrongNicknameCaseKick = Settings.IMP.MAIN.STRINGS.WRONG_NICKNAME_CASE_KICK;
    timesUp = serializer.deserialize(Settings.IMP.MAIN.STRINGS.TIMES_UP);
    registerSuccessful = serializer.deserialize(Settings.IMP.MAIN.STRINGS.REGISTER_SUCCESSFUL);
    if (Settings.IMP.MAIN.STRINGS.REGISTER_SUCCESSFUL_TITLE.isEmpty() && Settings.IMP.MAIN.STRINGS.REGISTER_SUCCESSFUL_SUBTITLE.isEmpty()) {
      registerSuccessfulTitle = null;
    } else {
      registerSuccessfulTitle = Title.title(
          serializer.deserialize(Settings.IMP.MAIN.STRINGS.REGISTER_SUCCESSFUL_TITLE),
          serializer.deserialize(Settings.IMP.MAIN.STRINGS.REGISTER_SUCCESSFUL_SUBTITLE),
          Settings.IMP.MAIN.CRACKED_TITLE_SETTINGS.toTimes()
      );
    }
    int loginAttempts = Settings.IMP.MAIN.LOGIN_ATTEMPTS;
    loginWrongPassword = new Component[loginAttempts];
    for (int i = 0; i < loginAttempts; ++i) {
      loginWrongPassword[i] = serializer.deserialize(MessageFormat.format(Settings.IMP.MAIN.STRINGS.LOGIN_WRONG_PASSWORD, i + 1));
    }
    loginWrongPasswordKick = serializer.deserialize(Settings.IMP.MAIN.STRINGS.LOGIN_WRONG_PASSWORD_KICK);
    totp = serializer.deserialize(Settings.IMP.MAIN.STRINGS.TOTP);
    if (Settings.IMP.MAIN.STRINGS.TOTP_TITLE.isEmpty() && Settings.IMP.MAIN.STRINGS.TOTP_SUBTITLE.isEmpty()) {
      totpTitle = null;
    } else {
      totpTitle = Title.title(
          serializer.deserialize(Settings.IMP.MAIN.STRINGS.TOTP_TITLE),
          serializer.deserialize(Settings.IMP.MAIN.STRINGS.TOTP_SUBTITLE),
          Settings.IMP.MAIN.CRACKED_TITLE_SETTINGS.toTimes()
      );
    }
    register = serializer.deserialize(Settings.IMP.MAIN.STRINGS.REGISTER);
    if (Settings.IMP.MAIN.STRINGS.REGISTER_TITLE.isEmpty() && Settings.IMP.MAIN.STRINGS.REGISTER_SUBTITLE.isEmpty()) {
      registerTitle = null;
    } else {
      registerTitle = Title.title(
          serializer.deserialize(Settings.IMP.MAIN.STRINGS.REGISTER_TITLE),
          serializer.deserialize(Settings.IMP.MAIN.STRINGS.REGISTER_SUBTITLE),
          Settings.IMP.MAIN.CRACKED_TITLE_SETTINGS.toTimes()
      );
    }
    login = new Component[loginAttempts];
    for (int i = 0; i < loginAttempts; ++i) {
      login[i] = serializer.deserialize(MessageFormat.format(Settings.IMP.MAIN.STRINGS.LOGIN, i + 1));
    }
    if (Settings.IMP.MAIN.STRINGS.LOGIN_TITLE.isEmpty() && Settings.IMP.MAIN.STRINGS.LOGIN_SUBTITLE.isEmpty()) {
      loginTitle = null;
    } else {
      loginTitle = Title.title(
          serializer.deserialize(MessageFormat.format(Settings.IMP.MAIN.STRINGS.LOGIN_TITLE, loginAttempts)),
          serializer.deserialize(MessageFormat.format(Settings.IMP.MAIN.STRINGS.LOGIN_SUBTITLE, loginAttempts)),
          Settings.IMP.MAIN.CRACKED_TITLE_SETTINGS.toTimes()
      );
    }
    registerDifferentPasswords = serializer.deserialize(Settings.IMP.MAIN.STRINGS.REGISTER_DIFFERENT_PASSWORDS);
    registerPasswordTooLong = serializer.deserialize(Settings.IMP.MAIN.STRINGS.REGISTER_PASSWORD_TOO_LONG);
    registerPasswordTooShort = serializer.deserialize(Settings.IMP.MAIN.STRINGS.REGISTER_PASSWORD_TOO_SHORT);
    registerPasswordUnsafe = serializer.deserialize(Settings.IMP.MAIN.STRINGS.REGISTER_PASSWORD_UNSAFE);
    loginSuccessful = serializer.deserialize(Settings.IMP.MAIN.STRINGS.LOGIN_SUCCESSFUL);
    sessionExpired = serializer.deserialize(Settings.IMP.MAIN.STRINGS.MOD_SESSION_EXPIRED);
    if (Settings.IMP.MAIN.STRINGS.LOGIN_SUCCESSFUL_TITLE.isEmpty() && Settings.IMP.MAIN.STRINGS.LOGIN_SUCCESSFUL_SUBTITLE.isEmpty()) {
      loginSuccessfulTitle = null;
    } else {
      loginSuccessfulTitle = Title.title(
          serializer.deserialize(Settings.IMP.MAIN.STRINGS.LOGIN_SUCCESSFUL_TITLE),
          serializer.deserialize(Settings.IMP.MAIN.STRINGS.LOGIN_SUCCESSFUL_SUBTITLE),
          Settings.IMP.MAIN.CRACKED_TITLE_SETTINGS.toTimes()
      );
    }

    migrationHash = Settings.IMP.MAIN.MIGRATION_HASH;
  }

  /**
   * Use {@link RegisteredPlayer#genHash(String)} or {@link RegisteredPlayer#setPassword}
   */
  @Deprecated()
  public static String genHash(String password) {
    return HASHER.hashToString(Settings.IMP.MAIN.BCRYPT_COST, password.toCharArray());
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
      if (Settings.IMP.MAIN.REGISTER_COMMAND.contains(command)) {
        return Command.REGISTER;
      } else if (Settings.IMP.MAIN.LOGIN_COMMAND.contains(command)) {
        return Command.LOGIN;
      } else if (Settings.IMP.MAIN.TOTP_COMMAND.contains(command)) {
        return Command.TOTP;
      } else {
        return Command.INVALID;
      }
    }
  }
}
