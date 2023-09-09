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
import java.util.Locale;
import java.util.function.Function;
import net.elytrium.limboauth.LimboAuth;
import net.elytrium.limboauth.Settings;
import net.elytrium.limboauth.model.RegisteredPlayer;
import net.elytrium.serializer.placeholders.Placeholders;
import org.jooq.DSLContext;
import org.jooq.impl.DSL;

public class ForceRegisterCommand implements SimpleCommand {

  private final LimboAuth plugin;
  private final DSLContext dslContext;

  public ForceRegisterCommand(LimboAuth plugin, DSLContext dslContext) {
    this.plugin = plugin;
    this.dslContext = dslContext;
  }

  @Override
  public void execute(SimpleCommand.Invocation invocation) {
    CommandSource source = invocation.source();
    String[] args = invocation.arguments();

    if (args.length == 2) {
      String nickname = args[0];
      String password = args[1];

      if (!this.plugin.getNicknameValidationPattern().matcher(nickname).matches()) {
        source.sendMessage(Settings.MESSAGES.forceRegisterIncorrectNickname);
        return;
      }

      Function<Throwable, Void> onError = e -> {
        this.plugin.handleSqlError(e);
        source.sendMessage((Placeholders.replace(Settings.MESSAGES.forceRegisterNotSuccessful, nickname)));
        return null;
      };

      String lowercaseNickname = nickname.toLowerCase(Locale.ROOT);
      this.dslContext.selectCount()
          .from(RegisteredPlayer.Table.INSTANCE)
          .where(DSL.field(RegisteredPlayer.Table.LOWERCASE_NICKNAME_FIELD).eq(lowercaseNickname))
          .fetchAsync()
          .thenAccept(countResult -> {
            if (countResult.get(0).value1() != 0) {
              source.sendMessage(Settings.MESSAGES.forceRegisterTakenNickname);
              return;
            }

            RegisteredPlayer player = new RegisteredPlayer(nickname, "", "").setPassword(password);
            this.dslContext.insertInto(RegisteredPlayer.Table.INSTANCE)
                .values(player)
                .executeAsync()
                .thenRun(() -> source.sendMessage(Placeholders.replace(Settings.MESSAGES.forceRegisterSuccessful, nickname)))
                .exceptionally(onError);
          }).exceptionally(onError);
    } else {
      source.sendMessage(Settings.MESSAGES.forceRegisterUsage);
    }
  }

  @Override
  public boolean hasPermission(SimpleCommand.Invocation invocation) {
    return Settings.IMP.commandPermissionState.forceRegister.hasPermission(invocation.source(), "limboauth.admin.forceregister");
  }
}
