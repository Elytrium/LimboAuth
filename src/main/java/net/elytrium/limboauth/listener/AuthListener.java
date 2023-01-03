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

import com.j256.ormlite.dao.Dao;
import com.j256.ormlite.stmt.UpdateBuilder;
import com.velocitypowered.api.event.PostOrder;
import com.velocitypowered.api.event.Subscribe;
import com.velocitypowered.api.event.connection.DisconnectEvent;
import com.velocitypowered.api.event.connection.PostLoginEvent;
import com.velocitypowered.api.event.connection.PreLoginEvent;
import com.velocitypowered.api.event.player.GameProfileRequestEvent;
import com.velocitypowered.api.proxy.InboundConnection;
import com.velocitypowered.api.util.UuidUtils;
import com.velocitypowered.proxy.connection.MinecraftConnection;
import com.velocitypowered.proxy.connection.client.InitialInboundConnection;
import com.velocitypowered.proxy.connection.client.InitialLoginSessionHandler;
import com.velocitypowered.proxy.connection.client.LoginInboundConnection;
import com.velocitypowered.proxy.protocol.packet.ServerLogin;
import java.lang.invoke.MethodHandle;
import java.lang.invoke.MethodHandles;
import java.sql.SQLException;
import java.util.Map;
import java.util.UUID;
import java.util.concurrent.TimeUnit;
import net.elytrium.java.commons.reflection.ReflectionException;
import net.elytrium.limboapi.api.event.LoginLimboRegisterEvent;
import net.elytrium.limboauth.LimboAuth;
import net.elytrium.limboauth.Settings;
import net.elytrium.limboauth.floodgate.FloodgateApiHolder;
import net.elytrium.limboauth.handler.AuthSessionHandler;
import net.elytrium.limboauth.model.RegisteredPlayer;
import net.elytrium.limboauth.model.SQLRuntimeException;

// TODO: Customizable events priority
public class AuthListener {

  private static final MethodHandle DELEGATE_FIELD;
  private static final MethodHandle LOGIN_FIELD;

  private final LimboAuth plugin;
  private final Dao<RegisteredPlayer, String> playerDao;
  private final FloodgateApiHolder floodgateApi;

  public AuthListener(LimboAuth plugin, Dao<RegisteredPlayer, String> playerDao, FloodgateApiHolder floodgateApi) {
    this.plugin = plugin;
    this.playerDao = playerDao;
    this.floodgateApi = floodgateApi;
  }

  @Subscribe
  public void onPreLoginEvent(PreLoginEvent event) {
    if (!event.getResult().isForceOfflineMode()) {
      if (this.plugin.isPremium(event.getUsername())) {
        event.setResult(PreLoginEvent.PreLoginComponentResult.forceOnlineMode());
      } else {
        event.setResult(PreLoginEvent.PreLoginComponentResult.forceOfflineMode());
      }
    } else {
      this.plugin.saveForceOfflineMode(event.getUsername());
    }
  }

  // Temporarily disabled because some clients send UUID version 4 (random UUID) even if the player is cracked
  private boolean isPremiumByIdentifiedKey(InboundConnection inbound) throws Throwable {
    LoginInboundConnection inboundConnection = (LoginInboundConnection) inbound;
    InitialInboundConnection initialInbound = (InitialInboundConnection) DELEGATE_FIELD.invokeExact(inboundConnection);
    MinecraftConnection connection = initialInbound.getConnection();
    InitialLoginSessionHandler handler = (InitialLoginSessionHandler) connection.getSessionHandler();

    ServerLogin packet = (ServerLogin) LOGIN_FIELD.invokeExact(handler);
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
    this.plugin.unsetForcedPreviously(event.getPlayer().getUsername());
  }

  @Subscribe
  public void onPostLogin(PostLoginEvent event) {
    Map<UUID, Runnable> postLoginTasks = this.plugin.getPostLoginTasks();
    UUID uuid = event.getPlayer().getUniqueId();
    if (postLoginTasks.containsKey(uuid)) {
      // We need to delay for player's client to finish switching the server, it takes a little time.
      this.plugin.getServer().getScheduler()
          .buildTask(this.plugin, () -> postLoginTasks.get(uuid).run())
          .delay(Settings.IMP.MAIN.PREMIUM_AND_FLOODGATE_MESSAGES_DELAY, TimeUnit.MILLISECONDS)
          .schedule();
    }
  }

  @Subscribe
  public void onLoginLimboRegister(LoginLimboRegisterEvent event) {
    if (this.plugin.needAuth(event.getPlayer())) {
      event.addOnJoinCallback(() -> this.plugin.authPlayer(event.getPlayer()));
    }
  }

  @Subscribe(order = PostOrder.FIRST)
  public void onGameProfileRequest(GameProfileRequestEvent event) {
    if (Settings.IMP.MAIN.SAVE_UUID && (this.floodgateApi == null || !this.floodgateApi.isFloodgatePlayer(event.getOriginalProfile().getId()))) {
      RegisteredPlayer registeredPlayer = AuthSessionHandler.fetchInfo(this.playerDao, event.getOriginalProfile().getId());

      if (registeredPlayer != null && !registeredPlayer.getUuid().isEmpty()) {
        event.setGameProfile(event.getOriginalProfile().withId(UUID.fromString(registeredPlayer.getUuid())));
        return;
      }
      registeredPlayer = AuthSessionHandler.fetchInfo(this.playerDao, event.getUsername());

      if (registeredPlayer != null) {
        String currentUuid = registeredPlayer.getUuid();

        if (currentUuid.isEmpty()) {
          try {
            registeredPlayer.setUuid(event.getGameProfile().getId().toString());
            this.playerDao.update(registeredPlayer);
          } catch (SQLException e) {
            throw new SQLRuntimeException(e);
          }
        } else {
          event.setGameProfile(event.getOriginalProfile().withId(UUID.fromString(currentUuid)));
        }
      }
    } else if (event.isOnlineMode()) {
      try {
        UpdateBuilder<RegisteredPlayer, String> updateBuilder = this.playerDao.updateBuilder();
        updateBuilder.where().eq(RegisteredPlayer.NICKNAME_FIELD, event.getUsername());
        updateBuilder.updateColumnValue(RegisteredPlayer.HASH_FIELD, "");
        updateBuilder.update();
      } catch (SQLException e) {
        throw new SQLRuntimeException(e);
      }
    }

    if (Settings.IMP.MAIN.FORCE_OFFLINE_UUID) {
      event.setGameProfile(event.getOriginalProfile().withId(UuidUtils.generateOfflinePlayerUuid(event.getUsername())));
    }

    if (!event.isOnlineMode() && !Settings.IMP.MAIN.OFFLINE_MODE_PREFIX.isEmpty()) {
      event.setGameProfile(event.getOriginalProfile().withName(Settings.IMP.MAIN.OFFLINE_MODE_PREFIX + event.getUsername()));
    }

    if (event.isOnlineMode() && !Settings.IMP.MAIN.ONLINE_MODE_PREFIX.isEmpty()) {
      event.setGameProfile(event.getOriginalProfile().withName(Settings.IMP.MAIN.ONLINE_MODE_PREFIX + event.getUsername()));
    }
  }

  static {
    try {
      DELEGATE_FIELD = MethodHandles.privateLookupIn(LoginInboundConnection.class, MethodHandles.lookup())
          .findGetter(LoginInboundConnection.class, "delegate", InitialInboundConnection.class);
      LOGIN_FIELD = MethodHandles.privateLookupIn(InitialLoginSessionHandler.class, MethodHandles.lookup())
          .findGetter(InitialLoginSessionHandler.class, "login", ServerLogin.class);
    } catch (NoSuchFieldException | IllegalAccessException e) {
      throw new ReflectionException(e);
    }
  }
}
