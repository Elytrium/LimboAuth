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

import com.velocitypowered.api.command.CommandSource;
import com.velocitypowered.api.command.SimpleCommand;
import com.velocitypowered.api.permission.Tristate;
import com.velocitypowered.api.proxy.Player;
import net.elytrium.java.commons.mc.serialization.Serializer;
import net.elytrium.limboauth.LimboAuth;
import net.elytrium.limboauth.Settings;
import net.kyori.adventure.audience.MessageType;
import net.kyori.adventure.text.Component;

public class DestroySessionCommand implements SimpleCommand {

  private final LimboAuth plugin;

  private final Component successful;
  private final Component notPlayer;

  public DestroySessionCommand(LimboAuth plugin) {
    this.plugin = plugin;

    Serializer serializer = LimboAuth.getSerializer();
    this.successful = serializer.deserialize(Settings.IMP.MAIN.STRINGS.DESTROY_SESSION_SUCCESSFUL);
    this.notPlayer = serializer.deserialize(Settings.IMP.MAIN.STRINGS.NOT_PLAYER);
  }

  @Override
  public void execute(SimpleCommand.Invocation invocation) {
    CommandSource source = invocation.source();

    if (source instanceof Player) {
      this.plugin.removePlayerFromCache(((Player) source).getUsername());
      source.sendMessage(this.successful, MessageType.SYSTEM);
    } else {
      source.sendMessage(this.notPlayer, MessageType.SYSTEM);
    }
  }

  @Override
  public boolean hasPermission(SimpleCommand.Invocation invocation) {
    return invocation.source().getPermissionValue("limboauth.commands.destroysession") == Tristate.TRUE;
  }
}
