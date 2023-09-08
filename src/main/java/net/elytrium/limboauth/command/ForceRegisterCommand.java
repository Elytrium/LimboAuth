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
import java.text.MessageFormat;
import java.util.Locale;
import java.util.function.Function;
import net.elytrium.commons.kyori.serialization.Serializer;
import net.elytrium.limboauth.LimboAuth;
import net.elytrium.limboauth.Settings;
import net.elytrium.limboauth.model.RegisteredPlayer;
import net.kyori.adventure.text.Component;
import org.jooq.DSLContext;
import org.jooq.impl.DSL;

public class ForceRegisterCommand implements SimpleCommand {

  private final LimboAuth plugin;
  private final Settings settings;
  private final DSLContext dslContext;

  private final String successful;
  private final String notSuccessful;
  private final Component usage;
  private final Component takenNickname;
  private final Component incorrectNickname;

  public ForceRegisterCommand(LimboAuth plugin, Settings settings, DSLContext dslContext) {
    this.plugin = plugin;
    this.settings = settings;
    this.dslContext = dslContext;

    Serializer serializer = plugin.getSerializer();
    this.successful = this.settings.main.strings.FORCE_REGISTER_SUCCESSFUL;
    this.notSuccessful = this.settings.main.strings.FORCE_REGISTER_NOT_SUCCESSFUL;
    this.usage = serializer.deserialize(this.settings.main.strings.FORCE_REGISTER_USAGE);
    this.takenNickname = serializer.deserialize(this.settings.main.strings.FORCE_REGISTER_TAKEN_NICKNAME);
    this.incorrectNickname = serializer.deserialize(this.settings.main.strings.FORCE_REGISTER_INCORRECT_NICKNAME);
  }

  @Override
  public void execute(SimpleCommand.Invocation invocation) {
    CommandSource source = invocation.source();
    String[] args = invocation.arguments();

    if (args.length == 2) {
      String nickname = args[0];
      String password = args[1];

      Serializer serializer = this.plugin.getSerializer();
      if (!this.plugin.getNicknameValidationPattern().matcher(nickname).matches()) {
        source.sendMessage(this.incorrectNickname);
        return;
      }

      Function<Throwable, Void> onError = e -> {
        this.plugin.handleSqlError(e);
        source.sendMessage(serializer.deserialize(MessageFormat.format(this.notSuccessful, nickname)));
        return null;
      };

      String lowercaseNickname = nickname.toLowerCase(Locale.ROOT);
      this.dslContext.selectCount()
          .from(RegisteredPlayer.Table.INSTANCE)
          .where(DSL.field(RegisteredPlayer.Table.LOWERCASE_NICKNAME_FIELD).eq(lowercaseNickname))
          .fetchAsync()
          .thenAccept(countResult -> {
            if (countResult.get(0).get(0, Integer.class) != 0) {
              source.sendMessage(this.takenNickname);
              return;
            }

            RegisteredPlayer player = new RegisteredPlayer(nickname, "", "").setPassword(this.settings, password);
            this.dslContext.insertInto(RegisteredPlayer.Table.INSTANCE)
                .values(player)
                .executeAsync()
                .thenRun(() -> source.sendMessage(serializer.deserialize(MessageFormat.format(this.successful, nickname))))
                .exceptionally(onError);
          }).exceptionally(onError);
    } else {
      source.sendMessage(this.usage);
    }
  }

  @Override
  public boolean hasPermission(SimpleCommand.Invocation invocation) {
    return this.settings.main.commandPermissionState.forceRegister
        .hasPermission(invocation.source(), "limboauth.admin.forceregister");
  }
}
