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
import com.velocitypowered.api.proxy.ProxyServer;
import java.util.List;
import java.util.Locale;
import net.elytrium.commons.velocity.commands.SuggestUtils;
import net.elytrium.limboauth.LimboAuth;
import net.elytrium.limboauth.Settings;
import net.elytrium.limboauth.event.ChangePasswordEvent;
import net.elytrium.limboauth.model.RegisteredPlayer;
import net.elytrium.serializer.placeholders.Placeholders;
import org.jooq.DSLContext;
import org.jooq.impl.DSL;

public class ForceChangePasswordCommand implements SimpleCommand {

  private final LimboAuth plugin;
  private final ProxyServer server;
  private final DSLContext dslContext;

  public ForceChangePasswordCommand(LimboAuth plugin, ProxyServer server, DSLContext dslContext) {
    this.plugin = plugin;
    this.server = server;
    this.dslContext = dslContext;
  }

  @Override
  public List<String> suggest(SimpleCommand.Invocation invocation) {
    return SuggestUtils.suggestPlayers(this.server, invocation.arguments(), 0);
  }

  @Override
  public void execute(SimpleCommand.Invocation invocation) {
    CommandSource source = invocation.source();
    String[] args = invocation.arguments();

    if (args.length == 2) {
      String nickname = args[0];
      String lowercaseNickname = nickname.toLowerCase(Locale.ROOT);
      String newPassword = args[1];

      this.dslContext.select(RegisteredPlayer.Table.HASH_FIELD)
          .from(RegisteredPlayer.Table.INSTANCE)
          .where(DSL.field(RegisteredPlayer.Table.LOWERCASE_NICKNAME_FIELD).eq(lowercaseNickname))
          .fetchAsync()
          .thenAccept(hashResult -> {
            if (hashResult.isEmpty()) {
              source.sendMessage((Placeholders.replace(Settings.MESSAGES.forceChangePasswordNotRegistered, nickname)));
              return;
            }

            final String oldHash = hashResult.get(0).value1();
            final String newHash = RegisteredPlayer.genHash(newPassword);

            this.dslContext.update(RegisteredPlayer.Table.INSTANCE)
                .set(RegisteredPlayer.Table.HASH_FIELD, newHash)
                .where(DSL.field(RegisteredPlayer.Table.LOWERCASE_NICKNAME_FIELD).eq(lowercaseNickname))
                .executeAsync();

            this.plugin.removePlayerFromCache(nickname);
            this.server.getPlayer(nickname)
                .ifPresent(player -> player.sendMessage((Placeholders.replace(Settings.MESSAGES.forceChangePasswordMessage, newPassword))));

            this.plugin.getServer().getEventManager().fireAndForget(new ChangePasswordEvent(nickname, null, oldHash, newPassword, newHash));

            source.sendMessage((Placeholders.replace(Settings.MESSAGES.forceChangePasswordSuccessful, nickname)));
          })
          .exceptionally(e -> {
            this.plugin.handleSqlError(e);
            source.sendMessage((Placeholders.replace(Settings.MESSAGES.forceChangePasswordNotSuccessful, nickname)));
            return null;
          });
    } else {
      source.sendMessage(Settings.MESSAGES.forceChangePasswordUsage);
    }
  }

  @Override
  public boolean hasPermission(SimpleCommand.Invocation invocation) {
    return Settings.IMP.commandPermissionState.forceChangePassword.hasPermission(invocation.source(), "limboauth.admin.forcechangepassword");
  }
}
