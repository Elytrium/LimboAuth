/*
 * Copyright (C) 2021-2023 Elytrium
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

package net.elytrium.limboauth.command.impl;

import net.elytrium.limboauth.LimboAuth;
import net.elytrium.limboauth.command.AbstractRawCommand;

public class DestroySessionCommand extends AbstractRawCommand {

  public DestroySessionCommand(LimboAuth plugin) {
    super(plugin);
  }

  /*
  public DestroySessionCommand(LimboAuth plugin) {
    super(plugin, "destroysession", "logout");
    this.permission("limboauth.commands.destroysession").permissionState(Settings.PERMISSION_STATES.destroySession);
  }

  @Override
  public void execute(RawCommand.Invocation invocation) {
    CommandSource source = invocation.source();
    if (source instanceof Player player) {
      this.plugin.getCacheManager().removePlayerFromCache(player.getUsername());
      player.sendMessage(Settings.MESSAGES.destroySessionSuccessful);
    } else {
      source.sendMessage(Settings.MESSAGES.notPlayer);
    }
  }
  */
}
