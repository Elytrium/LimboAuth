/*
 * Copyright (C) 2021-2024 Elytrium
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
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

package net.elytrium.limboauth.auth;

import com.velocitypowered.api.proxy.Player;
import com.velocitypowered.proxy.protocol.packet.PluginMessagePacket;
import io.netty.buffer.ByteBuf;
import java.nio.charset.StandardCharsets;
import java.util.concurrent.ScheduledFuture;
import java.util.concurrent.TimeUnit;
import net.elytrium.limboapi.api.Limbo;
import net.elytrium.limboapi.api.LimboSessionHandler;
import net.elytrium.limboapi.api.player.LimboPlayer;
import net.elytrium.limboauth.LimboAuth;
import net.elytrium.limboauth.Settings;
import net.elytrium.limboauth.api.events.PostAuthorizationEvent;
import net.elytrium.limboauth.api.events.PostRegisterEvent;
import net.elytrium.limboauth.api.events.TaskEvent;
import net.elytrium.limboauth.cache.CacheManager;
import net.elytrium.limboauth.data.Database;
import net.elytrium.limboauth.data.PlayerData;
import net.elytrium.limboauth.utils.AdvancedHashing;
import net.elytrium.limboauth.utils.adventure.BossBars;
import net.elytrium.serializer.placeholders.Placeholders;
import net.kyori.adventure.bossbar.BossBar;
import net.kyori.adventure.text.Component;
import org.bouncycastle.crypto.generators.OpenBSDBCrypt;
import org.checkerframework.checker.nullness.qual.Nullable;

public class AuthSessionHandler implements LimboSessionHandler {

  //TODO private static final CodeVerifier TOTP_CODE_VERIFIER = new DefaultCodeVerifier(new DefaultCodeGenerator(), new SystemTimeProvider()); // TODO check for analogs

  private final Player proxyPlayer;
  private final LimboAuth plugin;

  private final boolean loginOnlyByMod;

  private int authTime = Settings.HEAD.authTime - 1;

  private BossBar bossBar;

  @Nullable
  private PlayerData playerInfo; // TODO expiring cache

  private ScheduledFuture<?> task;

  private LimboPlayer player;
  private int attempts;
  private boolean totpState;
  private String tempPassword;
  private boolean tokenReceived;
  private boolean authorized;

  public AuthSessionHandler(Player proxyPlayer, LimboAuth plugin, @Nullable PlayerData playerInfo) {
    this.proxyPlayer = proxyPlayer;
    this.plugin = plugin;
    this.playerInfo = playerInfo;
    this.loginOnlyByMod = Settings.HEAD.mod.enabled && (Settings.HEAD.mod.loginOnlyByMod || (playerInfo != null && playerInfo.isOnlyByMod()));
    this.attempts = Settings.HEAD.loginAttempts;
  }

  @Override
  public void onSpawn(Limbo server, LimboPlayer player) {
    this.player = player;

    if (Settings.HEAD.disableFalling) {
      this.player.disableFalling();
    } else {
      this.player.enableFalling();
    }

    if (this.playerInfo == null) {
      Database.get().selectCount()
          .from(PlayerData.Table.INSTANCE)
          .where(
              PlayerData.Table.IP_FIELD.eq(this.proxyPlayer.getRemoteAddress().getAddress().getHostAddress())
                  .and(PlayerData.Table.REG_DATE_FIELD.ge(System.currentTimeMillis() - Settings.HEAD.ipLimitValidTime))
          ).fetchAsync()
          .thenAccept(registeredResult -> {
            if (registeredResult.get(0).value1() >= Settings.HEAD.ipLimitRegistrations) {
              this.proxyPlayer.disconnect(Settings.MESSAGES.ipLimitKick);
            } else {
              this.onSpawnAfterChecks();
            }
          })
          .exceptionally(t -> {
            this.proxyPlayer.disconnect(Settings.MESSAGES.databaseErrorKick);
            return null;
          });
    } else {
      if (!this.proxyPlayer.getUsername().equals(this.playerInfo.getNickname())) {
        this.proxyPlayer.disconnect(Placeholders.replace(Settings.MESSAGES.wrongNicknameCaseKick, this.playerInfo.getNickname(), this.proxyPlayer.getUsername()));
      } else {
        this.onSpawnAfterChecks();
      }
    }
  }

  private void onSpawnAfterChecks() {
    boolean bossBarEnabled = !this.loginOnlyByMod && Settings.HEAD.enableBossbar;
    final float multiplier = 1.0F / Settings.HEAD.authTime;
    this.task = this.player.getScheduledExecutor().scheduleWithFixedDelay(() -> {
      int remaining = this.authTime--;
      if (remaining == 0) {
        this.proxyPlayer.disconnect(Settings.MESSAGES.timesUp);
        this.task.cancel(true);
        this.task = null;
      } else if (bossBarEnabled) {
        BossBars.replaceName(this.bossBar, Settings.MESSAGES.bossbar, remaining);
        this.bossBar.progress(remaining * multiplier); // TODO check for 1.0000001
      }
    }, 1, 1, TimeUnit.SECONDS);

    if (bossBarEnabled) {
      this.bossBar = BossBars.replace(Settings.MESSAGES.bossbar, Settings.HEAD.authTime);
      this.proxyPlayer.showBossBar(this.bossBar);
    }

    if (!this.loginOnlyByMod) {
      this.sendMessage(true);
    }
  }

  @Override
  public void onChat(String message) {
    if (this.loginOnlyByMod || this.authorized) {
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
          PlayerData playerData = new PlayerData(this.proxyPlayer).setPassword(password);

          Database.get().insertInto(PlayerData.Table.INSTANCE).values(playerData).executeAsync();
          this.playerInfo = playerData;

          this.proxyPlayer.sendMessage(Settings.MESSAGES.registerSuccessful);
          if (Settings.MESSAGES.registerSuccessfulTitle != null) {
            this.proxyPlayer.showTitle(Settings.MESSAGES.registerSuccessfulTitle);
          }

          this.authorized = true;
          this.plugin.getServer().getEventManager()
              .fire(new PostRegisterEvent(this::finishAuth, this.player/*,TODO this.playerInfo*/, this.tempPassword))
              .thenAccept(this::finishAuth);
        }

        // {@code return} placed here (not above), because
        // AuthSessionHandler#checkPasswordsRepeat, AuthSessionHandler#checkPasswordLength, and AuthSessionHandler#checkPasswordStrength methods are
        // invoking Player#sendMessage that sends its own message in case if the return value is false
        // If we don't place {@code return} here, another message (AuthSessionHandler#sendMessage) will be sent
        return;
      } else if (command == Command.LOGIN && !this.totpState && this.playerInfo != null) {
        String password = args[1];
        this.saveTempPassword(password);

        if (!password.isEmpty() && this.checkPassword(this.proxyPlayer.getUsername(), this.playerInfo.getHash(), password)) {
          if (this.playerInfo.getTotpToken().isEmpty()) {
            this.authorized = true;
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
        if (true/*TODO TOTP_CODE_VERIFIER.isValidCode(this.playerInfo.getTotpToken(), args[1])*/) {
          this.authorized = true;
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
    if (Settings.HEAD.mod.enabled && packet instanceof PluginMessagePacket pluginMessage) {
      String channel = pluginMessage.getChannel();
      if (channel.equals("MC|Brand") || channel.equals("minecraft:brand")) {
        // Minecraft can't handle the plugin message immediately after going to the PLAY
        // state, so we have to postpone sending it
        if (Settings.HEAD.mod.enabled) {
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

        if (AdvancedHashing.sipHash(this.playerInfo.getLowercaseNickname().getBytes(StandardCharsets.UTF_8), issueTime) == hash) {
          this.finishAuth();
        } else {
          this.checkBruteforceAttempts();
          this.proxyPlayer.sendMessage(Settings.MESSAGES.modSessionExpired);
        }
      }
    }
  }

  private void checkBruteforceAttempts() {
    CacheManager cacheManager = this.plugin.getCacheManager();
    cacheManager.incrementBruteforceAttempts(this.proxyPlayer.getRemoteAddress().getAddress());
    if (cacheManager.getBruteforceAttempts(this.proxyPlayer.getRemoteAddress().getAddress()) >= Settings.HEAD.bruteforceMaxAttempts) {
      this.proxyPlayer.disconnect(Settings.MESSAGES.loginWrongPasswordKick);
    }
  }

  private void saveTempPassword(String password) {
    this.tempPassword = password;
  }

  @Override
  public void onDisconnect() {
    if (this.task != null) {
      this.task.cancel(true);
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
    return this.playerInfo == null && Settings.HEAD.registerNeedRepeatPassword ? argsLength == 3 : argsLength == 2;
  }

  private boolean checkPasswordsRepeat(String[] args) {
    if (!Settings.HEAD.registerNeedRepeatPassword || args[1].equals(args[2])) {
      return true;
    } else {
      this.proxyPlayer.sendMessage(Settings.MESSAGES.registerDifferentPasswords);
      return false;
    }
  }

  private boolean checkPasswordLength(String password) {
    if (password.length() < Settings.HEAD.minPasswordLength) {
      this.proxyPlayer.sendMessage(Settings.MESSAGES.registerPasswordTooShort);
      return false;
    } else {
      return true;
    }
  }

  private boolean checkPasswordStrength(String password) {
    if (Settings.HEAD.checkPasswordStrategy.checkPasswordStrength(this.plugin.getUnsafePasswords(), password)) {
      return true;
    }

    this.proxyPlayer.sendMessage(Settings.MESSAGES.registerPasswordUnsafe);
    return false;
  }

  private void finishLogin() {
    this.proxyPlayer.sendMessage(Settings.MESSAGES.loginSuccessful);
    if (Settings.MESSAGES.loginSuccessfulTitle != null) {
      this.proxyPlayer.showTitle(Settings.MESSAGES.loginSuccessfulTitle);
    }

    this.plugin.getCacheManager().clearBruteforceAttempts(this.proxyPlayer.getRemoteAddress().getAddress());

    this.plugin.getServer().getEventManager()
        .fire(new PostAuthorizationEvent(this::finishAuth, this.player/*,TODO this.playerInfo*/, this.tempPassword))
        .thenAccept(this::finishAuth);
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
    if (Settings.HEAD.clearTitleAfterLogin) {
      this.proxyPlayer.clearTitle();
    }

    this.plugin.getAuthManager().updateLoginData(this.proxyPlayer);
    this.plugin.getCacheManager().cacheSessionUser(this.proxyPlayer);
    this.player.disconnect();
  }

  public boolean checkPassword(String lowercaseNickname, String hash, String password) {
    if (hash.isEmpty()) {
      return false;
    }

    try {
      return OpenBSDBCrypt.checkPassword(hash.charAt(0) == '$' ? hash : '$' + hash/*just in case*/, password.getBytes(StandardCharsets.UTF_8));
    } catch (Throwable t) {
      if (Settings.HEAD.migrationHash != null) {
        if (Settings.HEAD.migrationHash.checkPassword(hash, password)) {
          Database.get().update(PlayerData.Table.INSTANCE)
              .set(PlayerData.Table.HASH_FIELD, PlayerData.genHash(password))
              .set(PlayerData.Table.TOKEN_ISSUED_AT_FIELD, System.currentTimeMillis())
              .where(PlayerData.Table.LOWERCASE_NICKNAME_FIELD.eq(lowercaseNickname))
              .executeAsync();
          return true;
        }
      }

      return false;
    }
  }

  // TODO brigadier
  private enum Command {

    INVALID,
    REGISTER,
    LOGIN,
    TOTP;

    static Command parse(String command) {
      return Settings.HEAD.registerCommand.contains(command) ? Command.REGISTER
          : Settings.HEAD.loginCommand.contains(command) ? Command.LOGIN
              : Settings.HEAD.totpCommand.contains(command) ? Command.TOTP
                  : Command.INVALID;
    }
  }
}
