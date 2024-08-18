/*
 * Copyright (C) 2021 - 2024 Elytrium
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
import java.util.Locale;
import javax.annotation.Nullable;
import net.elytrium.commons.kyori.serialization.Serializer;
import net.elytrium.limboauth.LimboAuth;
import net.elytrium.limboauth.Settings;
import net.elytrium.limboauth.event.ChangePasswordEvent;
import net.elytrium.limboauth.handler.AuthSessionHandler;
import net.elytrium.limboauth.helper.PasswordVerifier;
import net.elytrium.limboauth.model.RegisteredPlayer;
import net.elytrium.limboauth.model.SQLRuntimeException;
import net.kyori.adventure.text.Component;

public class ChangePasswordCommand extends RatelimitedCommand {

  private final LimboAuth plugin;
  private final Dao<RegisteredPlayer, String> playerDao;

  private final boolean needOldPass;
  private final Component notRegistered;
  private final Component wrongPassword;
  private final Component passwordTooLong;
  private final Component passwordTooShort;
  private final Component passwordUnsafe;
  private final Component successful;
  private final Component errorOccurred;
  private final Component usage;
  private final Component usageNoOldPass;
  private final Component notPlayer;

  public ChangePasswordCommand(LimboAuth plugin, Dao<RegisteredPlayer, String> playerDao) {
    this.plugin = plugin;
    this.playerDao = playerDao;

    Serializer serializer = LimboAuth.getSerializer();
    this.needOldPass = Settings.IMP.MAIN.CHANGE_PASSWORD_NEED_OLD_PASSWORD;
    this.notRegistered = serializer.deserialize(Settings.IMP.MAIN.STRINGS.NOT_REGISTERED);
    this.wrongPassword = serializer.deserialize(Settings.IMP.MAIN.STRINGS.WRONG_PASSWORD);
    // At this point, I've decided to keep invalid password messages with REGISTER_ prefix for backwards compatibility.
    this.passwordTooLong = serializer.deserialize(Settings.IMP.MAIN.STRINGS.REGISTER_PASSWORD_TOO_LONG);
    this.passwordTooShort = serializer.deserialize(Settings.IMP.MAIN.STRINGS.REGISTER_PASSWORD_TOO_SHORT);
    this.passwordUnsafe = serializer.deserialize(Settings.IMP.MAIN.STRINGS.REGISTER_PASSWORD_UNSAFE);
    this.successful = serializer.deserialize(Settings.IMP.MAIN.STRINGS.CHANGE_PASSWORD_SUCCESSFUL);
    this.errorOccurred = serializer.deserialize(Settings.IMP.MAIN.STRINGS.ERROR_OCCURRED);
    this.usage = serializer.deserialize(Settings.IMP.MAIN.STRINGS.CHANGE_PASSWORD_USAGE);
    this.usageNoOldPass = serializer.deserialize(Settings.IMP.MAIN.STRINGS.CHANGE_PASSWORD_NO_OLD_PASS_USAGE);
    this.notPlayer = serializer.deserialize(Settings.IMP.MAIN.STRINGS.NOT_PLAYER);
  }

  @Override
  public void execute(CommandSource source, String[] args) {
    if (source instanceof Player) {
      String usernameLowercase = ((Player) source).getUsername().toLowerCase(Locale.ROOT);
      RegisteredPlayer player = AuthSessionHandler.fetchInfoLowercased(this.playerDao, usernameLowercase);

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

        if (!AuthSessionHandler.checkPassword(args[0], player, this.playerDao)) {
          source.sendMessage(this.wrongPassword);
          return;
        }
      } else if (args.length < 1) {
        source.sendMessage(this.usageNoOldPass);
        return;
      }

      final PasswordVerifier passwordVerifier = new PasswordVerifier(this.plugin);
      final String newPassword = needOldPass ? args[1] : args[0];

      switch (passwordVerifier.checkPassword(newPassword)) {
        case PASSWORD_TOO_LONG -> source.sendMessage(this.passwordTooLong);
        case PASSWORD_TOO_SHORT -> source.sendMessage(this.passwordTooShort);
        case PASSWORD_UNSAFE -> source.sendMessage(this.passwordUnsafe);

        default -> this.performPasswordChange(source, player, usernameLowercase, needOldPass ? args[0] : null, newPassword);
      }
    } else {
      source.sendMessage(this.notPlayer);
    }
  }

  @Override
  public boolean hasPermission(SimpleCommand.Invocation invocation) {
    return Settings.IMP.MAIN.COMMAND_PERMISSION_STATE.CHANGE_PASSWORD
        .hasPermission(invocation.source(), "limboauth.commands.changepassword");
  }

  private void performPasswordChange(
      CommandSource source,
      RegisteredPlayer player,
      String usernameLowercase,
      @Nullable String oldPassword,
      String newPassword
  ) {
    try {
      final String oldHash = player.getHash();
      final String newHash = RegisteredPlayer.genHash(newPassword);

      UpdateBuilder<RegisteredPlayer, String> updateBuilder = this.playerDao.updateBuilder();
      updateBuilder.where().eq(RegisteredPlayer.LOWERCASE_NICKNAME_FIELD, usernameLowercase);
      updateBuilder.updateColumnValue(RegisteredPlayer.HASH_FIELD, newHash);
      updateBuilder.update();

      this.plugin.removePlayerFromCacheLowercased(usernameLowercase);

      this.plugin.getServer().getEventManager().fireAndForget(
          new ChangePasswordEvent(player, oldPassword, oldHash, newPassword, newHash));

      source.sendMessage(this.successful);
    } catch (SQLException e) {
      source.sendMessage(this.errorOccurred);
      throw new SQLRuntimeException(e);
    }
  }
}
