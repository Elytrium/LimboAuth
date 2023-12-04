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

package net.elytrium.limboauth.listener;

import com.velocitypowered.api.event.EventTask;
import com.velocitypowered.api.event.PostOrder;
import com.velocitypowered.api.event.Subscribe;
import com.velocitypowered.api.event.connection.DisconnectEvent;
import com.velocitypowered.api.event.connection.PostLoginEvent;
import com.velocitypowered.api.event.connection.PreLoginEvent;
import com.velocitypowered.api.event.player.GameProfileRequestEvent;
import com.velocitypowered.api.proxy.InboundConnection;
import com.velocitypowered.api.util.UuidUtils;
import com.velocitypowered.proxy.connection.client.InitialInboundConnection;
import com.velocitypowered.proxy.connection.client.InitialLoginSessionHandler;
import com.velocitypowered.proxy.connection.client.LoginInboundConnection;
import com.velocitypowered.proxy.protocol.packet.ServerLogin;
import java.lang.invoke.MethodHandle;
import java.util.UUID;
import java.util.concurrent.CompletableFuture;
import java.util.concurrent.TimeUnit;
import net.elytrium.limboapi.api.event.LoginLimboRegisterEvent;
import net.elytrium.limboauth.LimboAuth;
import net.elytrium.limboauth.Settings;
import net.elytrium.limboauth.auth.AuthManager;
import net.elytrium.limboauth.data.Database;
import net.elytrium.limboauth.data.PlayerData;
import net.elytrium.limboauth.floodgate.FloodgateApiHolder;
import net.elytrium.limboauth.utils.Reflection;

// TODO: Customizable events priority
public class AuthListener {

  private static final MethodHandle DELEGATE_FIELD;
  private static final MethodHandle LOGIN_FIELD;

  private final LimboAuth plugin;
  private final FloodgateApiHolder floodgateApi;

  public AuthListener(LimboAuth plugin, FloodgateApiHolder floodgateApi) {
    this.plugin = plugin;
    this.floodgateApi = floodgateApi;
  }

  @Subscribe
  public EventTask onPreLoginEvent(PreLoginEvent event) {
    if (!event.getResult().isForceOfflineMode()) {
      return EventTask.resumeWhenComplete(this.plugin.getHybridAuthManager().isPremium(event.getUsername()).thenAccept(isPremium -> {
        if (isPremium) {
          event.setResult(PreLoginEvent.PreLoginComponentResult.forceOnlineMode());
        } else {
          event.setResult(PreLoginEvent.PreLoginComponentResult.forceOfflineMode());
        }
      }));
    } else {
      this.plugin.getCacheManager().saveForceOfflineMode(event.getUsername());
      return null;
    }
  }

  // Temporarily disabled because some clients send UUID version 4 (random UUID) even if the player is cracked
  private boolean isPremiumByIdentifiedKey(InboundConnection inbound) throws Throwable {
    InitialInboundConnection inboundConnection = (InitialInboundConnection) AuthListener.DELEGATE_FIELD.invokeExact((LoginInboundConnection) inbound);
    ServerLogin packet = (ServerLogin) AuthListener.LOGIN_FIELD.invokeExact((InitialLoginSessionHandler) inboundConnection.getConnection().getActiveSessionHandler());
    if (packet == null) {
      return false;
    }

    UUID holder = packet.getHolderUuid();
    if (holder == null) {
      return false;
    }

    return holder.version() != 3;
  }

  @Subscribe
  public void onProxyDisconnect(DisconnectEvent event) {
    this.plugin.getCacheManager().unsetForcedPreviously(event.getPlayer().getUsername());
  }

  @Subscribe
  public void onPostLogin(PostLoginEvent event) {
    UUID uuid = event.getPlayer().getUniqueId();
    Runnable postLoginTask = this.plugin.getCacheManager().popPostLoginTask(uuid);
    if (postLoginTask != null) {
      // We need to delay for player's client to finish switching the server, it takes a little time.
      this.plugin.getServer().getScheduler()
          .buildTask(this.plugin, postLoginTask)
          .delay(Settings.HEAD.premiumAndFloodgateMessagesDelay, TimeUnit.MILLISECONDS)
          .schedule();
    }
  }

  @Subscribe
  public void onLoginLimboRegister(LoginLimboRegisterEvent event) {
    AuthManager authManager = this.plugin.getAuthManager();
    if (authManager.needAuth(event.getPlayer())) {
      event.addOnJoinCallback(() -> authManager.authPlayer(event.getPlayer()));
    }
  }

  @Subscribe(order = PostOrder.FIRST)
  public EventTask onGameProfileRequest(GameProfileRequestEvent event) {
    EventTask eventTask = null;
    Database database = this.plugin.getDatabase();
    if (Settings.HEAD.saveUuid && (this.floodgateApi == null || !this.floodgateApi.isFloodgatePlayer(event.getOriginalProfile().getId()))) {
      CompletableFuture<Void> completableFuture = new CompletableFuture<>();
      eventTask = EventTask.resumeWhenComplete(completableFuture);

      database.select(PlayerData.Table.UUID_FIELD)
          .from(PlayerData.Table.INSTANCE)
          .where(PlayerData.Table.NICKNAME_FIELD.eq(event.getUsername()))
          .fetchAsync()
          .thenAccept(uuidResult -> {
            if (!uuidResult.isEmpty()) {
              String uuid = uuidResult.get(0).value1();
              if (uuid != null && !uuid.isEmpty()) {
                event.setGameProfile(event.getOriginalProfile().withId(UUID.fromString(uuid)));
              } else {
                database.update(PlayerData.Table.INSTANCE)
                    .set(PlayerData.Table.UUID_FIELD, event.getGameProfile().getId().toString())
                    .where(PlayerData.Table.NICKNAME_FIELD.eq(event.getUsername()))
                    .executeAsync();
              }
            }
          })
          .thenAccept(completableFuture::complete);
    } else if (event.isOnlineMode()) {
      database.update(PlayerData.Table.INSTANCE)
          .set(PlayerData.Table.HASH_FIELD, "")
          .where(PlayerData.Table.NICKNAME_FIELD.eq(event.getUsername()))
          .executeAsync();
    }

    if (Settings.HEAD.forceOfflineUuid) {
      event.setGameProfile(event.getOriginalProfile().withId(UuidUtils.generateOfflinePlayerUuid(event.getUsername())));
    }

    if (!event.isOnlineMode() && !Settings.HEAD.offlineModePrefix.isEmpty()) {
      event.setGameProfile(event.getOriginalProfile().withName(Settings.HEAD.offlineModePrefix + event.getUsername()));
    }

    if (event.isOnlineMode() && !Settings.HEAD.onlineModePrefix.isEmpty()) {
      event.setGameProfile(event.getOriginalProfile().withName(Settings.HEAD.onlineModePrefix + event.getUsername()));
    }

    return eventTask;
  }

  static {
    DELEGATE_FIELD = Reflection.getter1(LoginInboundConnection.class, "delegate", InitialInboundConnection.class);
    LOGIN_FIELD = Reflection.getter1(InitialLoginSessionHandler.class, "login", ServerLogin.class);
  }
}
