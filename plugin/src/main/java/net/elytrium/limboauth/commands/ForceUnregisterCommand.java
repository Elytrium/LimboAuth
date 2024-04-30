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

import com.velocitypowered.api.command.CommandSource;
import com.velocitypowered.api.command.SimpleCommand;
import com.velocitypowered.api.proxy.ProxyServer;
import java.util.List;
import java.util.Locale;
import net.elytrium.commons.velocity.commands.SuggestUtils;
import net.elytrium.limboauth.LimboAuth;
import net.elytrium.limboauth.Settings;
import net.elytrium.limboauth.api.events.AuthUnregisterEvent;
import net.elytrium.limboauth.data.Database;
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

      ProxyServer server = this.plugin.getServer();
      server.getEventManager().fireAndForget(new AuthUnregisterEvent(playerNick));
      Database.get().deleteFrom(PlayerData.Table.INSTANCE)
          .where(PlayerData.Table.LOWERCASE_NICKNAME_FIELD.eq(playerNick.toLowerCase(Locale.ROOT)))
          .executeAsync()
          .thenRun(() -> {
            this.plugin.getCacheManager().removePlayerFromCache(playerNick);
            server.getPlayer(playerNick).ifPresent(player -> player.disconnect(Settings.MESSAGES.forceUnregisterKick));
            source.sendMessage(Placeholders.replace(Settings.MESSAGES.forceUnregisterSuccessful, playerNick));
          }).exceptionally(t -> {
            source.sendMessage(Placeholders.replace(Settings.MESSAGES.forceUnregisterNotSuccessful, playerNick));
            return null;
          });
    } else {
      source.sendMessage(Settings.MESSAGES.forceUnregisterUsage);
    }
  }

  @Override
  public List<String> suggest(SimpleCommand.Invocation invocation) {
    return SuggestUtils.suggestPlayers(this.plugin.getServer(), invocation.arguments(), 0);
  }

  @Override
  public boolean hasPermission(SimpleCommand.Invocation invocation) {
    return Settings.PERMISSION_STATES.forceUnregister.hasPermission(invocation.source(), "limboauth.admin.forceunregister");
  }
}
