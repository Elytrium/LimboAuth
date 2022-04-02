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

package net.elytrium.limboauth.command;

import com.j256.ormlite.dao.Dao;
import com.velocitypowered.api.command.CommandSource;
import com.velocitypowered.api.command.SimpleCommand;
import com.velocitypowered.api.permission.Tristate;
import com.velocitypowered.api.proxy.Player;
import java.sql.SQLException;
import java.util.Locale;
import net.elytrium.limboauth.LimboAuth;
import net.elytrium.limboauth.Settings;
import net.elytrium.limboauth.event.AuthUnregisterEvent;
import net.elytrium.limboauth.handler.AuthSessionHandler;
import net.elytrium.limboauth.model.RegisteredPlayer;
import net.kyori.adventure.text.Component;
import net.kyori.adventure.text.serializer.legacy.LegacyComponentSerializer;

public class UnregisterCommand implements SimpleCommand {

  private final LimboAuth plugin;
  private final Dao<RegisteredPlayer, String> playerDao;

  private final Component notPlayer;
  private final Component notRegistered;
  private final Component successful;
  private final Component errorOccurred;
  private final Component wrongPassword;
  private final Component usage;
  private final Component crackedCommand;

  public UnregisterCommand(LimboAuth plugin, Dao<RegisteredPlayer, String> playerDao) {
    this.plugin = plugin;
    this.playerDao = playerDao;

    this.notPlayer = LegacyComponentSerializer.legacyAmpersand().deserialize(Settings.IMP.MAIN.STRINGS.NOT_PLAYER);
    this.notRegistered = LegacyComponentSerializer.legacyAmpersand().deserialize(Settings.IMP.MAIN.STRINGS.NOT_REGISTERED);
    this.successful = LegacyComponentSerializer.legacyAmpersand().deserialize(Settings.IMP.MAIN.STRINGS.UNREGISTER_SUCCESSFUL);
    this.errorOccurred = LegacyComponentSerializer.legacyAmpersand().deserialize(Settings.IMP.MAIN.STRINGS.ERROR_OCCURRED);
    this.wrongPassword = LegacyComponentSerializer.legacyAmpersand().deserialize(Settings.IMP.MAIN.STRINGS.WRONG_PASSWORD);
    this.usage = LegacyComponentSerializer.legacyAmpersand().deserialize(Settings.IMP.MAIN.STRINGS.UNREGISTER_USAGE);
    this.crackedCommand = LegacyComponentSerializer.legacyAmpersand().deserialize(Settings.IMP.MAIN.STRINGS.CRACKED_COMMAND);
  }

  @Override
  public void execute(SimpleCommand.Invocation invocation) {
    CommandSource source = invocation.source();
    String[] args = invocation.arguments();

    if (!(source instanceof Player)) {
      source.sendMessage(this.notPlayer);
      return;
    }

    if (args.length == 2) {
      if (args[1].equalsIgnoreCase("confirm")) {
        String username = ((Player) source).getUsername();
        RegisteredPlayer player = AuthSessionHandler.fetchInfo(this.playerDao, username);
        if (player == null) {
          source.sendMessage(this.notRegistered);
        } else if (player.getHash().isEmpty()) {
          source.sendMessage(this.crackedCommand);
        } else if (AuthSessionHandler.checkPassword(args[0], player, this.playerDao)) {
          try {
            this.plugin.getServer().getEventManager().fireAndForget(new AuthUnregisterEvent(username));
            this.playerDao.deleteById(username.toLowerCase(Locale.ROOT));
            this.plugin.removePlayerFromCache(username);
            ((Player) source).disconnect(this.successful);
          } catch (SQLException e) {
            source.sendMessage(this.errorOccurred);
            e.printStackTrace();
          }
        } else {
          source.sendMessage(this.wrongPassword);
        }

        return;
      }
    }

    source.sendMessage(this.usage);
  }

  @Override
  public boolean hasPermission(SimpleCommand.Invocation invocation) {
    return invocation.source().getPermissionValue("limboauth.commands.unregister") != Tristate.FALSE;
  }
}
