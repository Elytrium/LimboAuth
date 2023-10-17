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
import java.util.List;
import java.util.Locale;
import net.elytrium.commons.velocity.commands.SuggestUtils;
import net.elytrium.limboauth.LimboAuth;
import net.elytrium.limboauth.Settings;
import net.elytrium.limboauth.events.AuthUnregisterEvent;
import net.elytrium.limboauth.data.PlayerData;
import net.elytrium.serializer.placeholders.Placeholders;

public class ForceUnregisterCommand implements SimpleCommand {

  private final LimboAuth plugin;

  public ForceUnregisterCommand(LimboAuth plugin) {
    this.plugin = plugin;
  }

  @Override
  public void execute(SimpleCommand.Invocation invocation) {
    CommandSource source = invocation.source();
    String[] args = invocation.arguments();

    if (args.length == 1) {
      String playerNick = args[0];

      this.plugin.getServer().getEventManager().fireAndForget(new AuthUnregisterEvent(playerNick));
      this.plugin.getDatabase().getContext().deleteFrom(PlayerData.Table.INSTANCE)
          .where(PlayerData.Table.LOWERCASE_NICKNAME_FIELD.eq(playerNick.toLowerCase(Locale.ROOT)))
          .executeAsync()
          .thenRun(() -> {
            this.plugin.removePlayerFromCache(playerNick);
            this.plugin.getServer().getPlayer(playerNick).ifPresent(player -> player.disconnect(Settings.MESSAGES.forceUnregisterKick));
            source.sendMessage(Placeholders.replace(Settings.MESSAGES.forceUnregisterSuccessful, playerNick));
          }).exceptionally(e -> {
            this.plugin.getDatabase().handleSqlError(e);
            source.sendMessage(Placeholders.replace(Settings.MESSAGES.forceUnregisterNotSuccessful, playerNick));
            return null;
          });
    } else {
      source.sendMessage(Settings.MESSAGES.forceUnregisterUsage);
    }
  }

  @Override
  public List<String> suggest(SimpleCommand.Invocation invocation) {
    return SuggestUtils.suggestPlayers(this.server, invocation.arguments(), 0);
  }

  @Override
  public boolean hasPermission(SimpleCommand.Invocation invocation) {
    return Settings.HEAD.commandPermissionState.forceUnregister.hasPermission(invocation.source(), "limboauth.admin.forceunregister");
  }
}
