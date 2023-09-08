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
import com.velocitypowered.api.proxy.Player;
import net.elytrium.commons.kyori.serialization.Serializer;
import net.elytrium.limboauth.LimboAuth;
import net.elytrium.limboauth.Settings;
import net.kyori.adventure.text.Component;

public class DestroySessionCommand implements SimpleCommand {

  private final LimboAuth plugin;
  private final Settings settings;

  private final Component successful;
  private final Component notPlayer;

  public DestroySessionCommand(LimboAuth plugin, Settings settings) {
    this.plugin = plugin;
    this.settings = settings;

    Serializer serializer = plugin.getSerializer();
    this.successful = serializer.deserialize(this.settings.main.strings.DESTROY_SESSION_SUCCESSFUL);
    this.notPlayer = serializer.deserialize(this.settings.main.strings.NOT_PLAYER);
  }

  @Override
  public void execute(SimpleCommand.Invocation invocation) {
    CommandSource source = invocation.source();

    if (source instanceof Player) {
      this.plugin.removePlayerFromCache(((Player) source).getUsername());
      source.sendMessage(this.successful);
    } else {
      source.sendMessage(this.notPlayer);
    }
  }

  @Override
  public boolean hasPermission(SimpleCommand.Invocation invocation) {
    return this.settings.main.commandPermissionState.destroySession
        .hasPermission(invocation.source(), "limboauth.commands.destroysession");
  }
}
