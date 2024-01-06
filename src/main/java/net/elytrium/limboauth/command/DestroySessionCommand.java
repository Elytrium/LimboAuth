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
import net.elytrium.limboauth.model.RegisteredPlayer;
import net.elytrium.limboauth.storage.PlayerStorage;
import net.kyori.adventure.text.Component;

public class DestroySessionCommand extends RatelimitedCommand {

  private final LimboAuth plugin;

  private final PlayerStorage playerStorage;

  private final Component successful;
  private final Component notPlayer;

  public DestroySessionCommand(LimboAuth plugin, PlayerStorage playerStorage) {
    this.plugin = plugin;
    this.playerStorage = playerStorage;

    Serializer serializer = LimboAuth.getSerializer();
    this.successful = serializer.deserialize(Settings.IMP.MAIN.STRINGS.DESTROY_SESSION_SUCCESSFUL);
    this.notPlayer = serializer.deserialize(Settings.IMP.MAIN.STRINGS.NOT_PLAYER);
  }

  @Override
  public void execute(CommandSource source, String[] args) {
    if (source instanceof Player) {
      RegisteredPlayer account = playerStorage.getAccount(((Player) source).getUsername());

      if(account != null) {
        account.setTokenIssuedAt(0L);
        source.sendMessage(this.successful);
      }

    } else {
      source.sendMessage(this.notPlayer);
    }
  }

  @Override
  public boolean hasPermission(SimpleCommand.Invocation invocation) {
    return Settings.IMP.MAIN.COMMAND_PERMISSION_STATE.DESTROY_SESSION
        .hasPermission(invocation.source(), "limboauth.commands.destroysession");
  }
}
