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

package net.elytrium.limboauth.command;

import com.velocitypowered.api.command.CommandSource;
import com.velocitypowered.api.command.SimpleCommand;
import com.velocitypowered.api.proxy.ProxyServer;
import net.elytrium.commons.kyori.serialization.Serializer;
import net.elytrium.commons.velocity.commands.SuggestUtils;
import net.elytrium.limboauth.LimboAuth;
import net.elytrium.limboauth.Settings;
import net.elytrium.limboauth.event.AuthUnregisterEvent;
import net.elytrium.limboauth.storage.PlayerStorage;
import net.kyori.adventure.text.Component;

import java.text.MessageFormat;
import java.util.List;

public class ForceUnregisterCommand extends RatelimitedCommand {

  private final LimboAuth plugin;
  private final ProxyServer server;
  private final PlayerStorage playerStorage;

  private final Component kick;
  private final String successful;
  private final String notSuccessful;
  private final Component usage;

  public ForceUnregisterCommand(LimboAuth plugin, ProxyServer server, PlayerStorage playerStorage) {
    this.plugin = plugin;
    this.server = server;
    this.playerStorage = playerStorage;

    Serializer serializer = LimboAuth.getSerializer();
    this.kick = serializer.deserialize(Settings.IMP.MAIN.STRINGS.FORCE_UNREGISTER_KICK);
    this.successful = Settings.IMP.MAIN.STRINGS.FORCE_UNREGISTER_SUCCESSFUL;
    this.notSuccessful = Settings.IMP.MAIN.STRINGS.FORCE_UNREGISTER_NOT_SUCCESSFUL;
    this.usage = serializer.deserialize(Settings.IMP.MAIN.STRINGS.FORCE_UNREGISTER_USAGE);
  }

  @Override
  public List<String> suggest(SimpleCommand.Invocation invocation) {
    return SuggestUtils.suggestPlayers(this.server, invocation.arguments(), 0);
  }

  @Override
  public void execute(CommandSource source, String[] args) {
    if (args.length == 1) {
      String playerNick = args[0];

      Serializer serializer = LimboAuth.getSerializer();
      this.plugin.getServer().getEventManager().fireAndForget(new AuthUnregisterEvent(playerNick));

      if(!playerStorage.unregister(playerNick)) {
        source.sendMessage(serializer.deserialize(MessageFormat.format(this.notSuccessful, playerNick)));
      }

      this.server.getPlayer(playerNick).ifPresent(player -> player.disconnect(this.kick));
      source.sendMessage(serializer.deserialize(MessageFormat.format(this.successful, playerNick)));
    } else {
      source.sendMessage(this.usage);
    }
  }

  @Override
  public boolean hasPermission(SimpleCommand.Invocation invocation) {
    return Settings.IMP.MAIN.COMMAND_PERMISSION_STATE.FORCE_UNREGISTER
        .hasPermission(invocation.source(), "limboauth.admin.forceunregister");
  }
}
