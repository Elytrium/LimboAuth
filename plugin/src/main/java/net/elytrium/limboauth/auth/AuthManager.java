/*
 * Copyright (C) 2023-2024 Elytrium
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

import com.velocitypowered.api.event.EventManager;
import com.velocitypowered.api.proxy.Player;
import java.nio.charset.StandardCharsets;
import java.util.Locale;
import java.util.UUID;
import java.util.function.Consumer;
import java.util.regex.Pattern;
import net.elytrium.limboauth.LimboAuth;
import net.elytrium.limboauth.Settings;
import net.elytrium.limboauth.api.events.PreEvent;
import net.elytrium.limboauth.api.events.PreRegisterEvent;
import net.elytrium.limboauth.api.events.TaskEvent;
import net.elytrium.limboauth.cache.CachedSessionUser;
import net.elytrium.limboauth.data.Database;
import net.elytrium.limboauth.data.PlayerData;
import net.elytrium.limboauth.utils.AdvancedHashing;
import org.bouncycastle.util.Pack;

public class AuthManager {

  public static final Pattern NICKNAME_VALIDATION_PATTERN = Pattern.compile(Settings.HEAD.allowedNicknameRegex);

  private final LimboAuth plugin;

  public AuthManager(LimboAuth plugin) {
    this.plugin = plugin;
  }

  public boolean needAuth(Player player) {
    CachedSessionUser sessionUser = this.plugin.getCacheManager().getSessionUser(player);
    if (sessionUser == null) {
      return true;
    }

    return !sessionUser.getInetAddress().equals(player.getRemoteAddress().getAddress()) || !sessionUser.getUsername().equals(player.getUsername());
  }

  public void authPlayer(Player player) {
    boolean isFloodgate = !Settings.HEAD.floodgateNeedAuth && this.plugin.getFloodgateApi().isFloodgatePlayer(player.getUniqueId());
    if (!isFloodgate && this.plugin.getCacheManager().isForcedPreviously(player.getUsername())) {
      this.plugin.getHybridAuthManager().isPremium(player.getUsername()).thenAccept(isPremium -> {
        if (isPremium) {
          player.disconnect(Settings.MESSAGES.reconnectKick);
        } else {
          this.authPlayerStage1(player, false);
        }
      });
    } else {
      this.authPlayerStage1(player, isFloodgate);
    }
  }

  private void authPlayerStage1(Player player, boolean isFloodgate) {
    if (this.plugin.getCacheManager().getBruteforceAttempts(player) >= Settings.HEAD.bruteforceMaxAttempts) {
      player.disconnect(Settings.MESSAGES.loginWrongPasswordKick);
      return;
    }

    String nickname = player.getUsername();
    String lowercaseNickname = nickname.toLowerCase(Locale.ROOT);
    if (!AuthManager.NICKNAME_VALIDATION_PATTERN.matcher((isFloodgate) ? nickname.substring(this.plugin.getFloodgateApi().getPrefixLength()) : nickname).matches()) {
      player.disconnect(Settings.MESSAGES.nicknameInvalidKick);
      return;
    }

    Database database = Database.get();
    UUID uniqueId = player.getUniqueId();
    database.selectFrom(PlayerData.Table.INSTANCE).where(PlayerData.Table.LOWERCASE_NICKNAME_FIELD.eq(lowercaseNickname)).limit(1).fetchAsync().thenAccept(resultByName -> {
      boolean onlineMode = player.isOnlineMode();
      PlayerData playerDataByName = resultByName.isEmpty() ? null : resultByName.get(0);
      if ((onlineMode || isFloodgate) && (playerDataByName == null || playerDataByName.getHash().isEmpty())) {
        database.selectFrom(PlayerData.Table.INSTANCE).where(PlayerData.Table.PREMIUM_UUID_FIELD.eq(uniqueId)).limit(1).fetchAsync().thenAccept(resultByUUID -> {
          PlayerData playerData = resultByUUID.isEmpty() ? null : resultByName.get(0);
          if (playerDataByName != null && playerData == null && playerDataByName.getHash().isEmpty()) {
            playerData = playerDataByName;
            playerData.setPremiumUuid(uniqueId);
            database.update(PlayerData.Table.INSTANCE)
                .set(PlayerData.Table.PREMIUM_UUID_FIELD, uniqueId)
                .where(PlayerData.Table.LOWERCASE_NICKNAME_FIELD.eq(lowercaseNickname))
                .executeAsync();
          }

          if (playerDataByName == null && playerData == null && Settings.HEAD.savePremiumAccounts) {
            database.insertInto(PlayerData.Table.INSTANCE).values(playerData = new PlayerData(player).setPremiumUuid(uniqueId)).executeAsync();
          }

          TaskEvent.Result eventResult = TaskEvent.Result.NORMAL;
          if (playerData == null || playerData.getHash().isEmpty()) {
            // Due to the current connection state, which is set to LOGIN there, we cannot send the packets
            // We need to wait for the PLAY connection state to set
            this.plugin.getCacheManager().pushPostLoginTask(uniqueId, () -> {
              if (onlineMode) {
                if (Settings.MESSAGES.loginPremiumMessage != null) {
                  player.sendMessage(Settings.MESSAGES.loginPremiumMessage);
                }

                if (Settings.MESSAGES.loginPremiumTitle != null) {
                  player.showTitle(Settings.MESSAGES.loginPremiumTitle);
                }
              } else {
                if (Settings.MESSAGES.loginFloodgate != null) {
                  player.sendMessage(Settings.MESSAGES.loginFloodgate);
                }

                if (Settings.MESSAGES.loginFloodgateTitle != null) {
                  player.showTitle(Settings.MESSAGES.loginFloodgateTitle);
                }
              }
            });

            eventResult = TaskEvent.Result.BYPASS;
          }

          this.authPlayerStage2(player, playerData, eventResult);
        });
      } else {
        this.authPlayerStage2(player, playerDataByName, TaskEvent.Result.NORMAL);
      }
    });
  }

  private void authPlayerStage2(Player player, PlayerData playerData, TaskEvent.Result result) {
    EventManager eventManager = this.plugin.getServer().getEventManager();
    if (playerData == null) {
      if (Settings.HEAD.disableRegistrations) {
        player.disconnect(Settings.MESSAGES.registrationsDisabledKick);
        return;
      }

      Consumer<TaskEvent> eventConsumer = (event) -> this.sendPlayer(event, null);
      eventManager.fire(new PreRegisterEvent(eventConsumer, result, player)).thenAccept(eventConsumer);
    } else {
      //TODO
      //Consumer<TaskEvent> eventConsumer = (event) -> this.sendPlayer(event, ((PreAuthorizationEvent) event).getPlayerInfo());
      //eventManager.fire(new PreAuthorizationEvent(eventConsumer, result, player, playerData)).thenAccept(eventConsumer);
    }
  }

  private void sendPlayer(TaskEvent event, PlayerData playerData) {
    Player player = ((PreEvent) event).getPlayer();
    switch (event.getResult()) {
      case NORMAL -> this.plugin.getAuthServer().spawnPlayer(player, new AuthSessionHandler(player, this.plugin, playerData));
      case BYPASS -> {
        this.plugin.getCacheManager().cacheSessionUser(player);
        this.updateLoginData(player);
        this.plugin.getLimboFactory().passLoginLimbo(player);
      }
      case CANCEL -> player.disconnect(event.getReason());
    }
  }

  public void updateLoginData(Player player) {
    String lowercaseNickname = player.getUsername().toLowerCase(Locale.ROOT);
    Database.get().update(PlayerData.Table.INSTANCE)
        .set(PlayerData.Table.LOGIN_IP_FIELD, player.getRemoteAddress().getAddress().getHostAddress())
        .set(PlayerData.Table.LOGIN_DATE_FIELD, System.currentTimeMillis())
        .where(PlayerData.Table.LOWERCASE_NICKNAME_FIELD.eq(lowercaseNickname))
        .executeAsync()
        .thenRun(() -> {
          if (Settings.HEAD.mod.enabled) {
            long issueTime = System.currentTimeMillis();
            byte[] data = new byte[8 * 2];
            Pack.longToBigEndian(issueTime, data, 0);
            Pack.longToBigEndian(AdvancedHashing.sipHash(lowercaseNickname.getBytes(StandardCharsets.UTF_8), issueTime), data, 8);
            player.sendPluginMessage(this.plugin.getChannelIdentifier(player), data);
          }
        });
  }
}
