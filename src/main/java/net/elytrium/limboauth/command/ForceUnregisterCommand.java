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
import com.velocitypowered.api.proxy.ProxyServer;
import java.text.MessageFormat;
import java.util.List;
import java.util.Locale;
import net.elytrium.commons.kyori.serialization.Serializer;
import net.elytrium.commons.velocity.commands.SuggestUtils;
import net.elytrium.limboauth.LimboAuth;
import net.elytrium.limboauth.Settings;
import net.elytrium.limboauth.event.AuthUnregisterEvent;
import net.elytrium.limboauth.model.RegisteredPlayer;
import net.kyori.adventure.text.Component;
import org.jooq.DSLContext;
import org.jooq.impl.DSL;

public class ForceUnregisterCommand implements SimpleCommand {

  private final LimboAuth plugin;
  private final Settings settings;
  private final ProxyServer server;
  private final DSLContext dslContext;

  private final Component kick;
  private final String successful;
  private final String notSuccessful;
  private final Component usage;

  public ForceUnregisterCommand(LimboAuth plugin, Settings settings, ProxyServer server, DSLContext dslContext) {
    this.plugin = plugin;
    this.settings = settings;
    this.server = server;
    this.dslContext = dslContext;

    Serializer serializer = plugin.getSerializer();
    this.kick = serializer.deserialize(this.settings.main.strings.FORCE_UNREGISTER_KICK);
    this.successful = this.settings.main.strings.FORCE_UNREGISTER_SUCCESSFUL;
    this.notSuccessful = this.settings.main.strings.FORCE_UNREGISTER_NOT_SUCCESSFUL;
    this.usage = serializer.deserialize(this.settings.main.strings.FORCE_UNREGISTER_USAGE);
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

      Serializer serializer = this.plugin.getSerializer();
      this.plugin.getServer().getEventManager().fireAndForget(new AuthUnregisterEvent(playerNick));
      this.dslContext.deleteFrom(RegisteredPlayer.Table.INSTANCE)
          .where(DSL.field(RegisteredPlayer.Table.LOWERCASE_NICKNAME_FIELD).eq(playerNick.toLowerCase(Locale.ROOT)))
          .executeAsync()
          .thenRun(() -> {
            this.plugin.removePlayerFromCache(playerNick);
            this.server.getPlayer(playerNick).ifPresent(player -> player.disconnect(this.kick));
            source.sendMessage(serializer.deserialize(MessageFormat.format(this.successful, playerNick)));
          }).exceptionally(e -> {
            this.plugin.handleSqlError(e);
            source.sendMessage(serializer.deserialize(MessageFormat.format(this.notSuccessful, playerNick)));
            return null;
          });
    } else {
      source.sendMessage(this.usage);
    }
  }

  @Override
  public boolean hasPermission(SimpleCommand.Invocation invocation) {
    return this.settings.main.commandPermissionState.forceUnregister
        .hasPermission(invocation.source(), "limboauth.admin.forceunregister");
  }
}
