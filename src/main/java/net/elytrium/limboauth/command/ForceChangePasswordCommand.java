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
import com.velocitypowered.api.proxy.ProxyServer;
import java.sql.SQLException;
import java.text.MessageFormat;
import java.util.List;
import java.util.Locale;
import net.elytrium.commons.kyori.serialization.Serializer;
import net.elytrium.commons.velocity.commands.SuggestUtils;
import net.elytrium.limboauth.LimboAuth;
import net.elytrium.limboauth.Settings;
import net.elytrium.limboauth.event.ChangePasswordEvent;
import net.elytrium.limboauth.handler.AuthSessionHandler;
import net.elytrium.limboauth.helper.PasswordVerifier;
import net.elytrium.limboauth.model.RegisteredPlayer;
import net.elytrium.limboauth.model.SQLRuntimeException;
import net.kyori.adventure.text.Component;

public class ForceChangePasswordCommand extends RatelimitedCommand {

  private final LimboAuth plugin;
  private final ProxyServer server;
  private final Dao<RegisteredPlayer, String> playerDao;

  private final String message;
  private final String successful;
  private final String notSuccessful;
  private final String notRegistered;
  private final Component usage;
  private final Component passwordTooLong;
  private final Component passwordTooShort;
  private final Component passwordUnsafe;

  private final boolean bypassPasswordCheck;

  public ForceChangePasswordCommand(LimboAuth plugin, ProxyServer server, Dao<RegisteredPlayer, String> playerDao) {
    this.plugin = plugin;
    this.server = server;
    this.playerDao = playerDao;

    this.message = Settings.IMP.MAIN.STRINGS.FORCE_CHANGE_PASSWORD_MESSAGE;
    this.successful = Settings.IMP.MAIN.STRINGS.FORCE_CHANGE_PASSWORD_SUCCESSFUL;
    this.notSuccessful = Settings.IMP.MAIN.STRINGS.FORCE_CHANGE_PASSWORD_NOT_SUCCESSFUL;
    this.notRegistered = Settings.IMP.MAIN.STRINGS.FORCE_CHANGE_PASSWORD_NOT_REGISTERED;

    Serializer serializer = LimboAuth.getSerializer();

    this.usage = serializer.deserialize(Settings.IMP.MAIN.STRINGS.FORCE_CHANGE_PASSWORD_USAGE);
    this.passwordTooLong = serializer.deserialize(Settings.IMP.MAIN.STRINGS.REGISTER_PASSWORD_TOO_LONG);
    this.passwordTooShort = serializer.deserialize(Settings.IMP.MAIN.STRINGS.REGISTER_PASSWORD_TOO_SHORT);
    this.passwordUnsafe = serializer.deserialize(Settings.IMP.MAIN.STRINGS.REGISTER_PASSWORD_UNSAFE);

    this.bypassPasswordCheck = Settings.IMP.MAIN.FORCE_CHANGE_PASSWORD_BYPASS_CHECK;
  }

  @Override
  public List<String> suggest(SimpleCommand.Invocation invocation) {
    return SuggestUtils.suggestPlayers(this.server, invocation.arguments(), 0);
  }

  @Override
  public void execute(CommandSource source, String[] args) {
    if (args.length == 2) {
      String newPassword = args[1];

      if (!this.bypassPasswordCheck) {
        final PasswordVerifier passwordVerifier = new PasswordVerifier(this.plugin);
        switch (passwordVerifier.checkPassword(newPassword)) {
          case PASSWORD_TOO_LONG -> source.sendMessage(this.passwordTooLong);
          case PASSWORD_TOO_SHORT -> source.sendMessage(this.passwordTooShort);
          case PASSWORD_UNSAFE -> source.sendMessage(this.passwordUnsafe);

          default -> this.performPasswordChange(source, args[0], newPassword);
        }
      } else {
        this.performPasswordChange(source, args[0], newPassword);
      }
    } else {
      source.sendMessage(this.usage);
    }
  }

  @Override
  public boolean hasPermission(SimpleCommand.Invocation invocation) {
    return Settings.IMP.MAIN.COMMAND_PERMISSION_STATE.FORCE_CHANGE_PASSWORD
        .hasPermission(invocation.source(), "limboauth.admin.forcechangepassword");
  }

  private void performPasswordChange(CommandSource source, String username, String newPassword) {
    Serializer serializer = LimboAuth.getSerializer();
    String usernameLowercased = username.toLowerCase(Locale.ROOT);
    RegisteredPlayer registeredPlayer = AuthSessionHandler.fetchInfoLowercased(this.playerDao, usernameLowercased);

    if (registeredPlayer == null) {
      source.sendMessage(serializer.deserialize(MessageFormat.format(this.notRegistered, username)));
      return;
    }

    final String oldHash = registeredPlayer.getHash();
    final String newHash = RegisteredPlayer.genHash(newPassword);

    try {
      UpdateBuilder<RegisteredPlayer, String> updateBuilder = this.playerDao.updateBuilder();
      updateBuilder.where().eq(RegisteredPlayer.LOWERCASE_NICKNAME_FIELD, usernameLowercased);
      updateBuilder.updateColumnValue(RegisteredPlayer.HASH_FIELD, newHash);
      updateBuilder.update();

      this.plugin.removePlayerFromCacheLowercased(usernameLowercased);
      this.server.getPlayer(username)
          .ifPresent(player -> player.sendMessage(serializer.deserialize(MessageFormat.format(this.message, newPassword))));

      this.plugin.getServer().getEventManager().fireAndForget(new ChangePasswordEvent(registeredPlayer, null, oldHash, newPassword, newHash));

      source.sendMessage(serializer.deserialize(MessageFormat.format(this.successful, username)));
    } catch (SQLException e) {
      source.sendMessage(serializer.deserialize(MessageFormat.format(this.notSuccessful, username)));
      throw new SQLRuntimeException(e);
    }
  }
}
