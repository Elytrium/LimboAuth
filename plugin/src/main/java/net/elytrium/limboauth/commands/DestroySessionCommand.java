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

package net.elytrium.limboauth.commands;

import com.velocitypowered.api.proxy.Player;
import java.util.List;
import net.elytrium.limboauth.LimboAuth;
import net.elytrium.limboauth.Settings;
import net.elytrium.limboauth.utils.command.Commands;

public class DestroySessionCommand {

  public static void init(LimboAuth plugin) {
    plugin.registerCommand(List.of("destroysession", "logout"), command -> command
        .requires(Commands.requirePermission(Settings.PERMISSION_STATES.destroySession, "limboauth.commands.destroysession"))
        .executes(Commands.executePlayer(player -> DestroySessionCommand.execute(plugin, player))));
  }

  private static void execute(LimboAuth plugin, Player player) {
    plugin.getCacheManager().removePlayerFromCache(player.getUsername());
    player.sendMessage(Settings.MESSAGES.destroySessionSuccessful);
  }
}
