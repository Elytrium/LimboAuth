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

import com.j256.ormlite.dao.Dao;
import com.velocitypowered.api.command.CommandSource;
import com.velocitypowered.api.command.SimpleCommand;
import java.sql.SQLException;
import java.text.MessageFormat;
import java.util.Locale;
import net.elytrium.commons.kyori.serialization.Serializer;
import net.elytrium.limboauth.LimboAuth;
import net.elytrium.limboauth.Settings;
import net.elytrium.limboauth.model.RegisteredPlayer;
import net.elytrium.limboauth.model.SQLRuntimeException;
import net.kyori.adventure.text.Component;

public class ForceRegisterCommand implements SimpleCommand {

  private final LimboAuth plugin;
  private final Dao<RegisteredPlayer, String> playerDao;

  private final String successful;
  private final String notSuccessful;
  private final Component usage;
  private final Component takenNickname;
  private final Component incorrectNickname;

  public ForceRegisterCommand(LimboAuth plugin, Dao<RegisteredPlayer, String> playerDao) {
    this.plugin = plugin;
    this.playerDao = playerDao;

    this.successful = Settings.IMP.MAIN.STRINGS.FORCE_REGISTER_SUCCESSFUL;
    this.notSuccessful = Settings.IMP.MAIN.STRINGS.FORCE_REGISTER_NOT_SUCCESSFUL;
    this.usage = LimboAuth.getSerializer().deserialize(Settings.IMP.MAIN.STRINGS.FORCE_REGISTER_USAGE);
    this.takenNickname = LimboAuth.getSerializer().deserialize(Settings.IMP.MAIN.STRINGS.FORCE_REGISTER_TAKEN_NICKNAME);
    this.incorrectNickname = LimboAuth.getSerializer().deserialize(Settings.IMP.MAIN.STRINGS.FORCE_REGISTER_INCORRECT_NICKNAME);
  }

  @Override
  public void execute(SimpleCommand.Invocation invocation) {
    CommandSource source = invocation.source();
    String[] args = invocation.arguments();

    if (args.length == 2) {
      String nickname = args[0];
      String password = args[1];

      Serializer serializer = LimboAuth.getSerializer();
      try {
        if (!this.plugin.getNicknameValidationPattern().matcher(nickname).matches()) {
          source.sendMessage(this.incorrectNickname);
          return;
        }

        String lowercaseNickname = nickname.toLowerCase(Locale.ROOT);
        if (this.playerDao.idExists(lowercaseNickname)) {
          source.sendMessage(this.takenNickname);
          return;
        }

        RegisteredPlayer player = new RegisteredPlayer(nickname, "", "").setPassword(password);
        this.playerDao.create(player);

        source.sendMessage(serializer.deserialize(MessageFormat.format(this.successful, nickname)));
      } catch (SQLException e) {
        source.sendMessage(serializer.deserialize(MessageFormat.format(this.notSuccessful, nickname)));
        throw new SQLRuntimeException(e);
      }
    } else {
      source.sendMessage(this.usage);
    }
  }

  @Override
  public boolean hasPermission(SimpleCommand.Invocation invocation) {
    return Settings.IMP.MAIN.COMMAND_PERMISSION_STATE.FORCE_REGISTER
        .hasPermission(invocation.source(), "limboauth.admin.forceregister");
  }
}
