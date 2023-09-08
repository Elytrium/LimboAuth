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
import java.text.MessageFormat;
import java.util.List;
import java.util.Locale;
import net.elytrium.commons.kyori.serialization.Serializer;
import net.elytrium.commons.velocity.commands.SuggestUtils;
import net.elytrium.limboauth.LimboAuth;
import net.elytrium.limboauth.Settings;
import net.elytrium.limboauth.event.ChangePasswordEvent;
import net.elytrium.limboauth.model.RegisteredPlayer;
import net.kyori.adventure.text.Component;
import org.jooq.DSLContext;
import org.jooq.impl.DSL;

public class ForceChangePasswordCommand implements SimpleCommand {

  private final LimboAuth plugin;
  private final Settings settings;
  private final ProxyServer server;
  private final DSLContext dslContext;

  private final String message;
  private final String successful;
  private final String notSuccessful;
  private final String notRegistered;
  private final Component usage;

  public ForceChangePasswordCommand(LimboAuth plugin, Settings settings, ProxyServer server, DSLContext dslContext) {
    this.plugin = plugin;
    this.settings = settings;
    this.server = server;
    this.dslContext = dslContext;

    this.message = this.settings.main.strings.FORCE_CHANGE_PASSWORD_MESSAGE;
    this.successful = this.settings.main.strings.FORCE_CHANGE_PASSWORD_SUCCESSFUL;
    this.notSuccessful = this.settings.main.strings.FORCE_CHANGE_PASSWORD_NOT_SUCCESSFUL;
    this.notRegistered = this.settings.main.strings.FORCE_CHANGE_PASSWORD_NOT_REGISTERED;
    this.usage = this.plugin.getSerializer().deserialize(this.settings.main.strings.FORCE_CHANGE_PASSWORD_USAGE);
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

      Serializer serializer = this.plugin.getSerializer();
      this.dslContext.select(RegisteredPlayer.Table.HASH_FIELD)
          .from(RegisteredPlayer.Table.INSTANCE)
          .where(DSL.field(RegisteredPlayer.Table.LOWERCASE_NICKNAME_FIELD).eq(lowercaseNickname))
          .fetchAsync()
          .thenAccept(hashResult -> {
            if (hashResult.isEmpty()) {
              source.sendMessage(serializer.deserialize(MessageFormat.format(this.notRegistered, nickname)));
              return;
            }

            final String oldHash = hashResult.get(0).get(0, String.class);
            final String newHash = RegisteredPlayer.genHash(this.settings, newPassword);

            this.dslContext.update(RegisteredPlayer.Table.INSTANCE)
                .set(RegisteredPlayer.Table.HASH_FIELD, newHash)
                .where(DSL.field(RegisteredPlayer.Table.LOWERCASE_NICKNAME_FIELD).eq(lowercaseNickname))
                .executeAsync();

            this.plugin.removePlayerFromCache(nickname);
            this.server.getPlayer(nickname)
                .ifPresent(player -> player.sendMessage(serializer.deserialize(MessageFormat.format(this.message, newPassword))));

            this.plugin.getServer().getEventManager().fireAndForget(new ChangePasswordEvent(nickname, null, oldHash, newPassword, newHash));

            source.sendMessage(serializer.deserialize(MessageFormat.format(this.successful, nickname)));
          })
          .exceptionally(e -> {
            this.plugin.handleSqlError(e);
            source.sendMessage(serializer.deserialize(MessageFormat.format(this.notSuccessful, nickname)));
            return null;
          });
    } else {
      source.sendMessage(this.usage);
    }
  }

  @Override
  public boolean hasPermission(SimpleCommand.Invocation invocation) {
    return this.settings.main.commandPermissionState.forceChangePassword
        .hasPermission(invocation.source(), "limboauth.admin.forcechangepassword");
  }
}
