/*
 * Copyright (C) 2021 - 2024 Elytrium
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

import com.google.common.io.ByteArrayDataInput;
import com.google.common.io.ByteArrayDataOutput;
import com.google.common.io.ByteStreams;
import com.velocitypowered.api.event.Subscribe;
import com.velocitypowered.api.event.connection.PluginMessageEvent;
import com.velocitypowered.api.proxy.ServerConnection;
import com.velocitypowered.api.proxy.messages.ChannelIdentifier;
import com.velocitypowered.api.proxy.messages.ChannelMessageSink;
import com.velocitypowered.api.proxy.messages.MinecraftChannelIdentifier;
import java.util.AbstractMap.SimpleEntry;
import java.util.Map;
import java.util.function.Function;
import net.elytrium.limboauth.LimboAuth;
import net.elytrium.limboauth.Settings;
import net.elytrium.limboauth.backend.Endpoint;
import net.elytrium.limboauth.backend.type.LongDatabaseEndpoint;
import net.elytrium.limboauth.backend.type.StringDatabaseEndpoint;
import net.elytrium.limboauth.backend.type.StringEndpoint;
import net.elytrium.limboauth.backend.type.UnknownEndpoint;
import net.elytrium.limboauth.model.RegisteredPlayer;

public class BackendEndpointsListener {

  public static final ChannelIdentifier API_CHANNEL = MinecraftChannelIdentifier.create("limboauth", "backend_api");

  public static final Map<String, Function<LimboAuth, Endpoint>> TYPES = Map.ofEntries(
      new SimpleEntry<>("available_endpoints", plugin -> new StringEndpoint(plugin, "available_endpoints",
          username -> String.join(",", Settings.IMP.MAIN.BACKEND_API.ENABLED_ENDPOINTS))),
      new SimpleEntry<>("premium_state", lauth -> new StringEndpoint(lauth, "premium_state",
          username -> lauth.isPremiumInternal(username).getState().name())),
      new SimpleEntry<>("hash", plugin -> new StringDatabaseEndpoint(plugin, "hash", RegisteredPlayer::getHash)),
      new SimpleEntry<>("totp_token", plugin -> new StringDatabaseEndpoint(plugin, "totp_token", RegisteredPlayer::getTotpToken)),
      new SimpleEntry<>("reg_date", plugin -> new LongDatabaseEndpoint(plugin, "reg_date", RegisteredPlayer::getRegDate)),
      new SimpleEntry<>("uuid", plugin -> new StringDatabaseEndpoint(plugin, "uuid", RegisteredPlayer::getUuid)),
      new SimpleEntry<>("premium_uuid", plugin -> new StringDatabaseEndpoint(plugin, "premium_uuid", RegisteredPlayer::getPremiumUuid)),
      new SimpleEntry<>("ip", plugin -> new StringDatabaseEndpoint(plugin, "ip", RegisteredPlayer::getIP)),
      new SimpleEntry<>("login_ip", plugin -> new StringDatabaseEndpoint(plugin, "login_ip", RegisteredPlayer::getLoginIp)),
      new SimpleEntry<>("login_date", plugin -> new LongDatabaseEndpoint(plugin, "login_date", RegisteredPlayer::getLoginDate)),
      new SimpleEntry<>("token_issued_at", plugin -> new LongDatabaseEndpoint(plugin, "token_issued_at", RegisteredPlayer::getTokenIssuedAt))
  );

  private final LimboAuth plugin;

  public BackendEndpointsListener(LimboAuth plugin) {
    this.plugin = plugin;

    plugin.getServer().getChannelRegistrar().register(API_CHANNEL);
  }

  @Subscribe
  public void onRequest(PluginMessageEvent event) {
    if (event.getIdentifier() != API_CHANNEL || !(event.getSource() instanceof ServerConnection server)) {
      return;
    }

    ByteArrayDataInput in = ByteStreams.newDataInput(event.getData());
    String dataType = in.readUTF();
    Function<LimboAuth, Endpoint> typeFunc = TYPES.get(dataType);
    if (typeFunc == null) {
      this.send(server, new UnknownEndpoint(this.plugin, dataType));
    } else {
      Endpoint endpoint = typeFunc.apply(this.plugin);
      endpoint.read(in);
      this.send(server, endpoint);
    }
  }

  private void send(ChannelMessageSink sink, Endpoint endpoint) {
    ByteArrayDataOutput output = ByteStreams.newDataOutput();
    endpoint.write(output);
    sink.sendPluginMessage(API_CHANNEL, output.toByteArray());
  }
}
