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
import com.j256.ormlite.stmt.UpdateBuilder;
import com.velocitypowered.api.command.CommandSource;
import com.velocitypowered.api.command.SimpleCommand;
import com.velocitypowered.api.proxy.Player;
import java.sql.SQLException;
import net.elytrium.commons.kyori.serialization.Serializer;
import net.elytrium.limboauth.LimboAuth;
import net.elytrium.limboauth.Settings;
import net.elytrium.limboauth.event.ChangePasswordEvent;
import net.elytrium.limboauth.handler.AuthSessionHandler;
import net.elytrium.limboauth.model.CMSUser;
import net.elytrium.limboauth.model.RegisteredPlayer;
import net.elytrium.limboauth.model.SQLRuntimeException;
import net.kyori.adventure.text.Component;

public class ChangePasswordCommand implements SimpleCommand {

  private final LimboAuth plugin;
  private final Dao<RegisteredPlayer, String> playerDao;
  private final Dao<CMSUser, String> cmsUserDao;

  private final boolean needOldPass;
  private final Component notRegistered;
  private final Component wrongPassword;
  private final Component successful;
  private final Component errorOccurred;
  private final Component usage;
  private final Component notPlayer;

  public ChangePasswordCommand(LimboAuth plugin, Dao<RegisteredPlayer, String> playerDao, Dao<CMSUser, String> cmsUserDao) {
    this.plugin = plugin;
    this.playerDao = playerDao;
    this.cmsUserDao = cmsUserDao;

    Serializer serializer = LimboAuth.getSerializer();
    this.needOldPass = Settings.IMP.MAIN.CHANGE_PASSWORD_NEED_OLD_PASSWORD;
    this.notRegistered = serializer.deserialize(Settings.IMP.MAIN.STRINGS.NOT_REGISTERED);
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

    if (!(source instanceof Player)) {
      source.sendMessage(this.notPlayer);
      return;
    }
    String username = ((Player) source).getUsername();
    RegisteredPlayer player = AuthSessionHandler.fetchInfo(this.playerDao, username);

    if (player == null) {
      source.sendMessage(this.notRegistered);
      return;
    }

    boolean onlineMode = player.getHash().isEmpty();
    boolean needOldPass = this.needOldPass && !onlineMode;
    if (needOldPass) {
      if (args.length < 2) {
        source.sendMessage(this.usage);
        return;
      }

      if (!AuthSessionHandler.checkPassword(args[0], player, this.playerDao, this.cmsUserDao)) {
        source.sendMessage(this.wrongPassword);
        return;
      }
    } else if (args.length < 1) {
      source.sendMessage(this.usage);
      return;
    }

    try {
      final String oldHash = player.getHash();
      final String newPassword = needOldPass ? args[1] : args[0];
      final String newHash = RegisteredPlayer.genHash(newPassword);

      UpdateBuilder<RegisteredPlayer, String> updateBuilder = this.playerDao.updateBuilder();
      updateBuilder.where().eq(Settings.IMP.DATABASE.COLUMN_NAMES.LOWERCASE_NICKNAME_FIELD, username.toLowerCase());
      updateBuilder.updateColumnValue(Settings.IMP.DATABASE.COLUMN_NAMES.HASH_FIELD, newHash);
      updateBuilder.update();

      this.plugin.removePlayerFromCache(username);

      this.plugin.getServer().getEventManager().fireAndForget(
          new ChangePasswordEvent(player, needOldPass ? args[0] : null, oldHash, newPassword, newHash));

      source.sendMessage(this.successful);
    } catch (SQLException e) {
      source.sendMessage(this.errorOccurred);
      throw new SQLRuntimeException(e);
    }
  }

  @Override
  public boolean hasPermission(SimpleCommand.Invocation invocation) {
    return Settings.IMP.MAIN.COMMAND_PERMISSION_STATE.CHANGE_PASSWORD
        .hasPermission(invocation.source(), "limboauth.commands.changepassword");
  }
}
