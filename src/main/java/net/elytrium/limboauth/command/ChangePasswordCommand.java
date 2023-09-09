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
import java.util.function.Consumer;
import net.elytrium.limboauth.LimboAuth;
import net.elytrium.limboauth.Settings;
import net.elytrium.limboauth.event.ChangePasswordEvent;
import net.elytrium.limboauth.model.RegisteredPlayer;
import org.jooq.DSLContext;
import org.jooq.impl.DSL;

public class ChangePasswordCommand implements SimpleCommand {

  private final LimboAuth plugin;
  private final DSLContext dslContext;

  public ChangePasswordCommand(LimboAuth plugin, DSLContext dslContext) {
    this.plugin = plugin;
    this.dslContext = dslContext;
  }

  @Override
  public void execute(SimpleCommand.Invocation invocation) {
    CommandSource source = invocation.source();
    String[] args = invocation.arguments();

    if (source instanceof Player player) {
      boolean needOldPass = Settings.IMP.changePasswordNeedOldPassword && !player.isOnlineMode();
      if (needOldPass) {
        if (args.length < 2) {
          source.sendMessage(Settings.MESSAGES.changePasswordUsage);
          return;
        }
      } else if (args.length < 1) {
        source.sendMessage(Settings.MESSAGES.changePasswordUsage);
        return;
      }

      String username = player.getUsername();
      String lowercaseNickname = username.toLowerCase(Locale.ROOT);
      Consumer<String> onCorrect = oldHash -> {
        final String newPassword = needOldPass ? args[1] : args[0];
        final String newHash = RegisteredPlayer.genHash(newPassword);

        this.dslContext.update(RegisteredPlayer.Table.INSTANCE)
            .set(RegisteredPlayer.Table.HASH_FIELD, newHash)
            .where(DSL.field(RegisteredPlayer.Table.NICKNAME_FIELD).eq(username))
            .executeAsync();

        this.plugin.removePlayerFromCache(username);

        this.plugin.getServer().getEventManager().fireAndForget(new ChangePasswordEvent(username, needOldPass ? args[0] : null, oldHash, newPassword, newHash));

        source.sendMessage(Settings.MESSAGES.changePasswordSuccessful);
      };

      RegisteredPlayer.checkPassword(this.dslContext, lowercaseNickname, needOldPass ? args[0] : null,
          () -> source.sendMessage(Settings.MESSAGES.notRegistered),
          () -> onCorrect.accept(null),
          onCorrect,
          () -> source.sendMessage(Settings.MESSAGES.wrongPassword),
          e -> source.sendMessage(Settings.MESSAGES.errorOccurred)
      );
    } else {
      source.sendMessage(Settings.MESSAGES.notPlayer);
    }
  }

  @Override
  public boolean hasPermission(SimpleCommand.Invocation invocation) {
    return Settings.IMP.commandPermissionState.changePassword.hasPermission(invocation.source(), "limboauth.commands.changepassword");
  }
}
