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
import java.util.Locale;
import net.elytrium.limboauth.LimboAuth;
import net.elytrium.limboauth.Settings;
import net.elytrium.limboauth.event.AuthUnregisterEvent;
import net.elytrium.limboauth.model.RegisteredPlayer;
import org.jooq.DSLContext;
import org.jooq.impl.DSL;

public class UnregisterCommand implements SimpleCommand {

  private final LimboAuth plugin;
  private final DSLContext dslContext;

  public UnregisterCommand(LimboAuth plugin, DSLContext dslContext) {
    this.plugin = plugin;
    this.dslContext = dslContext;
  }

  @Override
  public void execute(SimpleCommand.Invocation invocation) {
    CommandSource source = invocation.source();
    String[] args = invocation.arguments();

    if (source instanceof Player) {
      if (args.length == 2) {
        if (Settings.IMP.confirmKeyword.equalsIgnoreCase(args[1])) {
          String username = ((Player) source).getUsername();
          String lowercaseNickname = username.toLowerCase(Locale.ROOT);
          RegisteredPlayer.checkPassword(this.dslContext, lowercaseNickname, args[0],
              () -> source.sendMessage(Settings.MESSAGES.notRegistered),
              () -> source.sendMessage(Settings.MESSAGES.crackedCommand),
              h -> {
                this.plugin.getServer().getEventManager().fireAndForget(new AuthUnregisterEvent(username));
                this.dslContext.deleteFrom(RegisteredPlayer.Table.INSTANCE)
                    .where(DSL.field(RegisteredPlayer.Table.LOWERCASE_NICKNAME_FIELD).eq(lowercaseNickname))
                    .executeAsync()
                    .exceptionally((e) -> {
                      source.sendMessage(Settings.MESSAGES.errorOccurred);
                      this.plugin.handleSqlError(e);
                      return null;
                    });
                this.plugin.removePlayerFromCache(lowercaseNickname);
                ((Player) source).disconnect(Settings.MESSAGES.unregisterSuccessful);
              },
              () -> source.sendMessage(Settings.MESSAGES.wrongPassword),
              (e) -> source.sendMessage(Settings.MESSAGES.errorOccurred)
          );
          return;
        }
      }

      source.sendMessage(Settings.MESSAGES.unregisterUsage);
    } else {
      source.sendMessage(Settings.MESSAGES.notPlayer);
    }
  }

  @Override
  public boolean hasPermission(SimpleCommand.Invocation invocation) {
    return Settings.IMP.commandPermissionState.unregister
        .hasPermission(invocation.source(), "limboauth.commands.unregister");
  }
}
