/*
 * Copyright (C) 2021 - 2022 Elytrium
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
import com.j256.ormlite.dao.Dao;
import com.velocitypowered.api.proxy.Player;
import dev.samstevens.totp.code.CodeVerifier;
import dev.samstevens.totp.code.DefaultCodeGenerator;
import dev.samstevens.totp.code.DefaultCodeVerifier;
import dev.samstevens.totp.time.SystemTimeProvider;
import java.nio.charset.StandardCharsets;
import java.sql.SQLException;
import java.text.MessageFormat;
import java.util.List;
import java.util.Locale;
import java.util.UUID;
import java.util.concurrent.ScheduledFuture;
import java.util.concurrent.TimeUnit;
import java.util.stream.Collectors;
import net.elytrium.java.commons.mc.serialization.Serializer;
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
import net.kyori.adventure.audience.MessageType;
import net.kyori.adventure.bossbar.BossBar;
import net.kyori.adventure.text.Component;
import net.kyori.adventure.title.Title;
import org.checkerframework.checker.nullness.qual.Nullable;

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
  @Nullable
  private static Title loginSuccessfulTitle;
  @Nullable
  private static MigrationHash migrationHash;

  private final Dao<RegisteredPlayer, String> playerDao;
  private final Player proxyPlayer;
  private final LimboAuth plugin;

  private final long joinTime = System.currentTimeMillis();
  private final BossBar bossBar = BossBar.bossBar(
      Component.empty(),
      1.0F,
      bossbarColor,
      bossbarOverlay
  );

  @Nullable
  private RegisteredPlayer playerInfo;

  private ScheduledFuture<?> authMainTask;

  private LimboPlayer player;
  private String ip;
  private int attempts = Settings.IMP.MAIN.LOGIN_ATTEMPTS;
  private boolean totpState;

  public AuthSessionHandler(Dao<RegisteredPlayer, String> playerDao, Player proxyPlayer, LimboAuth plugin, @Nullable RegisteredPlayer playerInfo) {
    this.playerDao = playerDao;
    this.proxyPlayer = proxyPlayer;
    this.plugin = plugin;
    this.playerInfo = playerInfo;
  }

  @Override
  public void onSpawn(Limbo server, LimboPlayer player) {
    this.player = player;
    this.ip = this.proxyPlayer.getRemoteAddress().getAddress().getHostAddress();

    this.player.disableFalling();

    Serializer serializer = LimboAuth.getSerializer();

    if (this.playerInfo == null) {
      try {
        List<RegisteredPlayer> alreadyRegistered = this.playerDao.queryForEq("IP", this.ip);
        if (alreadyRegistered != null) {
          int sizeOfValidRegistrations = alreadyRegistered.size();
          if (Settings.IMP.MAIN.IP_LIMIT_VALID_TIME > 0) {
            sizeOfValidRegistrations = sizeOfValidRegistrations - (int) alreadyRegistered.stream()
                    .filter(registeredPlayer -> registeredPlayer.getRegDate() < System.currentTimeMillis() - Settings.IMP.MAIN.IP_LIMIT_VALID_TIME)
                    .count();
          }

          if (sizeOfValidRegistrations >= Settings.IMP.MAIN.IP_LIMIT_REGISTRATIONS) {
            this.proxyPlayer.disconnect(ipLimitKick);
            return;
          }
        }
      } catch (SQLException e) {
        e.printStackTrace();
        this.proxyPlayer.disconnect(databaseErrorKick);
        return;
      }
    } else {
      if (!this.proxyPlayer.getUsername().equals(this.playerInfo.getNickname())) {
        this.proxyPlayer.disconnect(serializer.deserialize(
            MessageFormat.format(wrongNicknameCaseKick, this.playerInfo.getNickname(), this.proxyPlayer.getUsername()))
        );
        return;
      }
    }

    boolean bossBarEnabled = Settings.IMP.MAIN.ENABLE_BOSSBAR;
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

    this.sendMessage(true);
  }

  @Override
  public void onChat(String message) {
    String[] args = message.split(" ");
    if (args.length != 0 && this.checkArgsLength(args.length)) {
      Command command = Command.parse(args[0]);
      if (command == Command.REGISTER && !this.totpState && this.playerInfo == null) {
        if (this.checkPasswordsRepeat(args) && this.checkPasswordLength(args[1]) && this.checkPasswordStrength(args[1])) {
          String username = this.proxyPlayer.getUsername();
          RegisteredPlayer registeredPlayer = new RegisteredPlayer(
              username,
              username.toLowerCase(Locale.ROOT),
              genHash(args[1]),
              this.ip,
              "",
              System.currentTimeMillis(),
              this.proxyPlayer.getUniqueId().toString(),
              ""
          );

          try {
            this.playerDao.create(registeredPlayer);
            this.playerInfo = registeredPlayer;
          } catch (SQLException e) {
            e.printStackTrace();
            this.proxyPlayer.disconnect(databaseErrorKick);
          }

          this.proxyPlayer.sendMessage(registerSuccessful, MessageType.SYSTEM);
          if (registerSuccessfulTitle != null) {
            this.proxyPlayer.showTitle(registerSuccessfulTitle);
          }

          this.plugin.getServer().getEventManager()
              .fire(new PostRegisterEvent(this::finishAuth, this.player, this.playerInfo))
              .thenAcceptAsync(this::finishAuth);
        }

        // {@code return} placed here (not above), because
        // AuthSessionHandler#checkPasswordsRepeat, AuthSessionHandler#checkPasswordLength, and AuthSessionHandler#checkPasswordStrength methods are
        // invoking Player#sendMessage that sends its own message in case if the return value is false.
        // If we don't place {@code return} here, an another message (AuthSessionHandler#sendMessage) will be sent.
        return;
      } else if (command == Command.LOGIN && !this.totpState && this.playerInfo != null) {
        if (args[1].length() > 0 && checkPassword(args[1], this.playerInfo, this.playerDao)) {
          if (this.playerInfo.getTotpToken().isEmpty()) {
            this.finishLogin();
          } else {
            this.totpState = true;
            this.sendMessage(true);
          }
        } else if (--this.attempts != 0) {
          this.proxyPlayer.sendMessage(loginWrongPassword[this.attempts - 1], MessageType.SYSTEM);
        } else {
          this.proxyPlayer.disconnect(loginWrongPasswordKick);
        }

        return;
      } else if (command == Command.TOTP && this.totpState && this.playerInfo != null) {
        if (TOTP_CODE_VERIFIER.isValidCode(this.playerInfo.getTotpToken(), args[1])) {
          this.finishLogin();
          return;
        }
      }
    }

    this.sendMessage(false);
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
      this.proxyPlayer.sendMessage(totp, MessageType.SYSTEM);
      if (sendTitle && totpTitle != null) {
        this.proxyPlayer.showTitle(totpTitle);
      }
    } else if (this.playerInfo == null) {
      this.proxyPlayer.sendMessage(register, MessageType.SYSTEM);
      if (sendTitle && registerTitle != null) {
        this.proxyPlayer.showTitle(registerTitle);
      }
    } else {
      this.proxyPlayer.sendMessage(login[this.attempts - 1], MessageType.SYSTEM);
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
      this.proxyPlayer.sendMessage(registerDifferentPasswords, MessageType.SYSTEM);
      return false;
    }
  }

  private boolean checkPasswordLength(String password) {
    int length = password.length();
    if (length > Settings.IMP.MAIN.MAX_PASSWORD_LENGTH) {
      this.proxyPlayer.sendMessage(registerPasswordTooLong, MessageType.SYSTEM);
      return false;
    } else if (length < Settings.IMP.MAIN.MIN_PASSWORD_LENGTH) {
      this.proxyPlayer.sendMessage(registerPasswordTooShort, MessageType.SYSTEM);
      return false;
    } else {
      return true;
    }
  }

  private boolean checkPasswordStrength(String password) {
    if (Settings.IMP.MAIN.CHECK_PASSWORD_STRENGTH && this.plugin.getUnsafePasswords().contains(password)) {
      this.proxyPlayer.sendMessage(registerPasswordUnsafe, MessageType.SYSTEM);
      return false;
    } else {
      return true;
    }
  }

  private void finishLogin() {
    this.proxyPlayer.sendMessage(loginSuccessful, MessageType.SYSTEM);
    if (loginSuccessfulTitle != null) {
      this.proxyPlayer.showTitle(loginSuccessfulTitle);
    }

    this.plugin.getServer().getEventManager()
        .fire(new PostAuthorizationEvent(this::finishAuth, this.player, this.playerInfo))
        .thenAcceptAsync(this::finishAuth);
  }

  private void finishAuth(TaskEvent event) {
    if (Settings.IMP.MAIN.CRACKED_TITLE_SETTINGS.CLEAR_AFTER_LOGIN) {
      this.proxyPlayer.clearTitle();
    }

    if (event.getResult() == TaskEvent.Result.CANCEL) {
      this.proxyPlayer.disconnect(event.getReason());
      return;
    } else if (event.getResult() == TaskEvent.Result.WAIT) {
      return;
    }

    // Update player ip in the database
    this.playerInfo.setIP(this.ip);
    try {
      this.playerDao.update(this.playerInfo);
    } catch (SQLException e) {
      e.printStackTrace();
      this.proxyPlayer.disconnect(databaseErrorKick);
      return;
    }

    this.plugin.cacheAuthUser(this.proxyPlayer);
    this.player.disconnect();
  }

  public static void reload() {
    Serializer serializer = LimboAuth.getSerializer();
    bossbarColor = BossBar.Color.valueOf(Settings.IMP.MAIN.BOSSBAR_COLOR.toUpperCase(Locale.ROOT));
    bossbarOverlay = BossBar.Overlay.valueOf(Settings.IMP.MAIN.BOSSBAR_OVERLAY.toUpperCase(Locale.ROOT));
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
    if (Settings.IMP.MAIN.STRINGS.LOGIN_SUCCESSFUL_TITLE.isEmpty() && Settings.IMP.MAIN.STRINGS.LOGIN_SUCCESSFUL_SUBTITLE.isEmpty()) {
      loginSuccessfulTitle = null;
    } else {
      loginSuccessfulTitle = Title.title(
          serializer.deserialize(Settings.IMP.MAIN.STRINGS.LOGIN_SUCCESSFUL_TITLE),
          serializer.deserialize(Settings.IMP.MAIN.STRINGS.LOGIN_SUCCESSFUL_SUBTITLE),
          Settings.IMP.MAIN.CRACKED_TITLE_SETTINGS.toTimes()
      );
    }
    if (Settings.IMP.MAIN.MIGRATION_HASH.isEmpty()) {
      migrationHash = null;
    } else {
      migrationHash = MigrationHash.valueOf(Settings.IMP.MAIN.MIGRATION_HASH);
    }
  }

  public static boolean checkPassword(String password, RegisteredPlayer player, Dao<RegisteredPlayer, String> playerDao) {
    String hash = player.getHash();
    boolean isCorrect = HASH_VERIFIER.verify(
        password.getBytes(StandardCharsets.UTF_8),
        hash.replace("BCRYPT$", "$2a$").getBytes(StandardCharsets.UTF_8)
    ).verified;

    if (!isCorrect && migrationHash != null) {
      isCorrect = migrationHash.checkPassword(hash, password);
      if (isCorrect) {
        player.setHash(genHash(password));
        try {
          playerDao.update(player);
        } catch (SQLException e) {
          e.printStackTrace();
          return false;
        }
      }
    }

    return isCorrect;
  }

  public static RegisteredPlayer fetchInfo(Dao<RegisteredPlayer, String> playerDao, UUID uuid) {
    List<RegisteredPlayer> playerList = null;
    try {
      playerList = playerDao.queryForEq("PREMIUMUUID", uuid.toString());
    } catch (SQLException e) {
      e.printStackTrace();
    }

    return (playerList != null ? playerList.size() : 0) == 0 ? null : playerList.get(0);
  }

  public static RegisteredPlayer fetchInfo(Dao<RegisteredPlayer, String> playerDao, String nickname) {
    List<RegisteredPlayer> playerList = null;
    try {
      playerList = playerDao.queryForEq("LOWERCASENICKNAME", nickname.toLowerCase(Locale.ROOT));
    } catch (SQLException e) {
      e.printStackTrace();
    }

    return (playerList != null ? playerList.size() : 0) == 0 ? null : playerList.get(0);
  }

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
