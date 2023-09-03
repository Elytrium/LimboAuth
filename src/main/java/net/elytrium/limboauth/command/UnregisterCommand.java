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
import net.elytrium.commons.kyori.serialization.Serializer;
import net.elytrium.limboauth.LimboAuth;
import net.elytrium.limboauth.Settings;
import net.elytrium.limboauth.event.AuthUnregisterEvent;
import net.elytrium.limboauth.model.RegisteredPlayer;
import net.kyori.adventure.text.Component;
import org.jooq.DSLContext;
import org.jooq.impl.DSL;

public class UnregisterCommand implements SimpleCommand {

  private final LimboAuth plugin;
  private final DSLContext dslContext;

  private final String confirmKeyword;
  private final Component notPlayer;
  private final Component notRegistered;
  private final Component successful;
  private final Component errorOccurred;
  private final Component wrongPassword;
  private final Component usage;
  private final Component crackedCommand;

  public UnregisterCommand(LimboAuth plugin, DSLContext dslContext) {
    this.plugin = plugin;
    this.dslContext = dslContext;

    Serializer serializer = LimboAuth.getSerializer();
    this.confirmKeyword = Settings.IMP.MAIN.CONFIRM_KEYWORD;
    this.notPlayer = serializer.deserialize(Settings.IMP.MAIN.STRINGS.NOT_PLAYER);
    this.notRegistered = serializer.deserialize(Settings.IMP.MAIN.STRINGS.NOT_REGISTERED);
    this.successful = serializer.deserialize(Settings.IMP.MAIN.STRINGS.UNREGISTER_SUCCESSFUL);
    this.errorOccurred = serializer.deserialize(Settings.IMP.MAIN.STRINGS.ERROR_OCCURRED);
    this.wrongPassword = serializer.deserialize(Settings.IMP.MAIN.STRINGS.WRONG_PASSWORD);
    this.usage = serializer.deserialize(Settings.IMP.MAIN.STRINGS.UNREGISTER_USAGE);
    this.crackedCommand = serializer.deserialize(Settings.IMP.MAIN.STRINGS.CRACKED_COMMAND);
  }

  @Override
  public void execute(SimpleCommand.Invocation invocation) {
    CommandSource source = invocation.source();
    String[] args = invocation.arguments();

    if (source instanceof Player) {
      if (args.length == 2) {
        if (this.confirmKeyword.equalsIgnoreCase(args[1])) {
          String username = ((Player) source).getUsername();
          String lowercaseNickname = username.toLowerCase(Locale.ROOT);
          RegisteredPlayer.checkPassword(this.dslContext, lowercaseNickname, args[0],
              () -> source.sendMessage(this.notRegistered),
              () -> source.sendMessage(this.crackedCommand),
              h -> {
                this.plugin.getServer().getEventManager().fireAndForget(new AuthUnregisterEvent(username));
                this.dslContext.deleteFrom(RegisteredPlayer.Table.INSTANCE)
                    .where(DSL.field(RegisteredPlayer.LOWERCASE_NICKNAME_FIELD).eq(lowercaseNickname))
                    .executeAsync()
                    .exceptionally((e) -> {
                      source.sendMessage(this.errorOccurred);
                      // TODO: logger
                      return null;
                    });
                this.plugin.removePlayerFromCache(lowercaseNickname);
                ((Player) source).disconnect(this.successful);
              },
              () -> source.sendMessage(this.wrongPassword),
              (e) -> source.sendMessage(this.errorOccurred));
          return;
        }
      }

      source.sendMessage(this.usage);
    } else {
      source.sendMessage(this.notPlayer);
    }
  }

  @Override
  public boolean hasPermission(SimpleCommand.Invocation invocation) {
    return Settings.IMP.MAIN.COMMAND_PERMISSION_STATE.UNREGISTER
        .hasPermission(invocation.source(), "limboauth.commands.unregister");
  }
}
