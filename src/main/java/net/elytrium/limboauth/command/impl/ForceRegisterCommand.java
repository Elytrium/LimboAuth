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

package net.elytrium.limboauth.command.impl;

import com.velocitypowered.api.command.CommandSource;
import com.velocitypowered.api.command.SimpleCommand;
import java.util.Locale;
import net.elytrium.limboauth.LimboAuth;
import net.elytrium.limboauth.Settings;
import net.elytrium.limboauth.data.Database;
import net.elytrium.limboauth.data.PlayerData;
import net.elytrium.limboauth.serialization.ComponentSerializer;
import net.elytrium.serializer.placeholders.Placeholders;

public class ForceRegisterCommand implements SimpleCommand {

  private final LimboAuth plugin;

  public ForceRegisterCommand(LimboAuth plugin) {
    this.plugin = plugin;
  }

  @Override
  public void execute(SimpleCommand.Invocation invocation) {
    CommandSource source = invocation.source();
    String[] args = invocation.arguments();

    if (args.length == 2) {
      String nickname = args[0];
      if (!this.plugin.getNicknameValidationPattern().matcher(nickname).matches()) {
        source.sendMessage(Settings.MESSAGES.forceRegisterIncorrectNickname);
        return;
      }

      Database database = this.plugin.getDatabase();
      database.selectCount()
          .from(PlayerData.Table.INSTANCE)
          .where(PlayerData.Table.LOWERCASE_NICKNAME_FIELD.eq(nickname.toLowerCase(Locale.ROOT)))
          .fetchAsync()
          .thenAccept(countResult -> {
            if (countResult.get(0).value1() != 0) {
              source.sendMessage(Settings.MESSAGES.forceRegisterTakenNickname);
              return;
            }

            PlayerData player = new PlayerData(nickname, "", "").setPassword(args[1]);
            database.insertInto(PlayerData.Table.INSTANCE)
                .values(player)
                .executeAsync()
                .thenRun(() -> source.sendMessage(ComponentSerializer.replace(Settings.MESSAGES.forceRegisterSuccessful, nickname)))
                .exceptionally(t -> {
                  source.sendMessage(ComponentSerializer.replace(Settings.MESSAGES.forceRegisterNotSuccessful, nickname));
                  return null;
                });
          }).exceptionally(t -> {
            source.sendMessage(ComponentSerializer.replace(Settings.MESSAGES.forceRegisterNotSuccessful, nickname));
            return null;
          });
    } else {
      source.sendMessage(Settings.MESSAGES.forceRegisterUsage);
    }
  }

  @Override
  public boolean hasPermission(SimpleCommand.Invocation invocation) {
    return Settings.PERMISSION_STATES.forceRegister.hasPermission(invocation.source(), "limboauth.admin.forceregister");
  }
}
