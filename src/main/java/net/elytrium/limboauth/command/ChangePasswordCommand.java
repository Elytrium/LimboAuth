/*
 * Copyright (C) 2021 - 2022 Elytrium
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
import com.j256.ormlite.stmt.UpdateBuilder;
import com.velocitypowered.api.command.CommandSource;
import com.velocitypowered.api.command.SimpleCommand;
import com.velocitypowered.api.permission.Tristate;
import com.velocitypowered.api.proxy.Player;
import java.sql.SQLException;
import net.elytrium.java.commons.mc.serialization.Serializer;
import net.elytrium.limboauth.LimboAuth;
import net.elytrium.limboauth.Settings;
import net.elytrium.limboauth.handler.AuthSessionHandler;
import net.elytrium.limboauth.model.RegisteredPlayer;
import net.kyori.adventure.text.Component;

public class ChangePasswordCommand implements SimpleCommand {

  private final Dao<RegisteredPlayer, String> playerDao;

  private final boolean needOldPass;
  private final Component notRegistered;
  private final Component crackedCommand;
  private final Component wrongPassword;
  private final Component successful;
  private final Component errorOccurred;
  private final Component usage;
  private final Component notPlayer;

  public ChangePasswordCommand(Dao<RegisteredPlayer, String> playerDao) {
    this.playerDao = playerDao;

    Serializer serializer = LimboAuth.getSerializer();
    this.needOldPass = Settings.IMP.MAIN.CHANGE_PASSWORD_NEED_OLD_PASSWORD;
    this.notRegistered = serializer.deserialize(Settings.IMP.MAIN.STRINGS.NOT_REGISTERED);
    this.crackedCommand = serializer.deserialize(Settings.IMP.MAIN.STRINGS.CRACKED_COMMAND);
    this.wrongPassword = serializer.deserialize(Settings.IMP.MAIN.STRINGS.WRONG_PASSWORD);
    this.successful = serializer.deserialize(Settings.IMP.MAIN.STRINGS.CHANGE_PASSWORD_SUCCESSFUL);
    this.errorOccurred = serializer.deserialize(Settings.IMP.MAIN.STRINGS.ERROR_OCCURRED);
    this.usage = serializer.deserialize(Settings.IMP.MAIN.STRINGS.CHANGE_PASSWORD_USAGE);
    this.notPlayer = serializer.deserialize(Settings.IMP.MAIN.STRINGS.NOT_PLAYER);
  }

  @Override
  public void execute(SimpleCommand.Invocation invocation) {
    CommandSource source = invocation.source();
    String[] args = invocation.arguments();

    if (source instanceof Player) {
      if (this.needOldPass ? args.length == 2 : args.length == 1) {
        if (this.needOldPass) {
          RegisteredPlayer player = AuthSessionHandler.fetchInfo(this.playerDao, ((Player) source).getUsername());
          if (player == null) {
            source.sendMessage(this.notRegistered);
            return;
          } else if (player.getHash().isEmpty()) {
            source.sendMessage(this.crackedCommand);
          } else if (!AuthSessionHandler.checkPassword(args[0], player, this.playerDao)) {
            source.sendMessage(this.wrongPassword);
            return;
          }
        }

        try {
          UpdateBuilder<RegisteredPlayer, String> updateBuilder = this.playerDao.updateBuilder();
          updateBuilder.where().eq(RegisteredPlayer.NICKNAME_FIELD, ((Player) source).getUsername());
          updateBuilder.updateColumnValue(RegisteredPlayer.HASH_FIELD, AuthSessionHandler.genHash(this.needOldPass ? args[1] : args[0]));
          updateBuilder.update();

          source.sendMessage(this.successful);
        } catch (SQLException e) {
          source.sendMessage(this.errorOccurred);
          e.printStackTrace();
        }
      } else {
        source.sendMessage(this.usage);
      }
    } else {
      source.sendMessage(this.notPlayer);
    }
  }

  @Override
  public boolean hasPermission(SimpleCommand.Invocation invocation) {
    return invocation.source().getPermissionValue("limboauth.commands.changepassword") == Tristate.TRUE;
  }
}
