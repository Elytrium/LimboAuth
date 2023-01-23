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

import com.j256.ormlite.dao.Dao;
import com.velocitypowered.api.command.CommandSource;
import com.velocitypowered.api.command.SimpleCommand;
import com.velocitypowered.api.proxy.ProxyServer;
import java.sql.SQLException;
import java.text.MessageFormat;
import java.util.List;
import java.util.Locale;
import net.elytrium.commons.kyori.serialization.Serializer;
import net.elytrium.commons.velocity.commands.SuggestUtils;
import net.elytrium.limboauth.LimboAuth;
import net.elytrium.limboauth.Settings;
import net.elytrium.limboauth.event.AuthUnregisterEvent;
import net.elytrium.limboauth.model.RegisteredPlayer;
import net.elytrium.limboauth.model.SQLRuntimeException;
import net.kyori.adventure.text.Component;

public class ForceUnregisterCommand implements SimpleCommand {

  private final LimboAuth plugin;
  private final ProxyServer server;
  private final Dao<RegisteredPlayer, String> playerDao;

  private final Component kick;
  private final String successful;
  private final String notSuccessful;
  private final Component usage;

  public ForceUnregisterCommand(LimboAuth plugin, ProxyServer server, Dao<RegisteredPlayer, String> playerDao) {
    this.plugin = plugin;
    this.server = server;
    this.playerDao = playerDao;

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
  public void execute(SimpleCommand.Invocation invocation) {
    CommandSource source = invocation.source();
    String[] args = invocation.arguments();

    if (args.length == 1) {
      String playerNick = args[0];

      Serializer serializer = LimboAuth.getSerializer();
      try {
        this.plugin.getServer().getEventManager().fireAndForget(new AuthUnregisterEvent(playerNick));
        this.playerDao.deleteById(playerNick.toLowerCase(Locale.ROOT));
        this.plugin.removePlayerFromCache(playerNick);
        this.server.getPlayer(playerNick).ifPresent(player -> player.disconnect(this.kick));
        source.sendMessage(serializer.deserialize(MessageFormat.format(this.successful, playerNick)));
      } catch (SQLException e) {
        source.sendMessage(serializer.deserialize(MessageFormat.format(this.notSuccessful, playerNick)));
        throw new SQLRuntimeException(e);
      }
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
