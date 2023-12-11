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

package net.elytrium.limboauth.command.impl;

import com.velocitypowered.api.command.CommandSource;
import com.velocitypowered.api.command.SimpleCommand;
import com.velocitypowered.api.proxy.Player;
import net.elytrium.limboauth.LimboAuth;
import net.elytrium.limboauth.Settings;
import net.elytrium.limboauth.command.BaseRawCommand;
import net.elytrium.limboauth.command.BaseSubcommand;

public class DestroySessionCommand extends BaseRawCommand {

  public DestroySessionCommand(LimboAuth plugin) {
    super(command -> command.permission(Settings.PERMISSION_STATES.destroySession, "limboauth.commands.destroysession"), plugin, "destroysession", "logout");
  }

  @Override
  public void execute(SimpleCommand.Invocation invocation) {
    CommandSource source = invocation.source();
    if (source instanceof Player player) {
      this.plugin.getCacheManager().removePlayerFromCache(player.getUsername());
      source.sendMessage(Settings.MESSAGES.destroySessionSuccessful);
    } else {
      source.sendMessage(Settings.MESSAGES.notPlayer);
    }
  }

  @Override
  public boolean hasPermission(SimpleCommand.Invocation invocation) {
    return Settings.PERMISSION_STATES.destroySession.hasPermission(invocation.source(), "limboauth.commands.destroysession");
  }
}
