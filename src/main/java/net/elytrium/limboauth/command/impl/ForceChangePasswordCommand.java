/*
 * Copyright (C) 2021-2023 Elytrium
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
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

package net.elytrium.limboauth.command.impl;

import com.velocitypowered.api.command.CommandSource;
import com.velocitypowered.api.command.SimpleCommand;
import com.velocitypowered.api.proxy.ProxyServer;
import java.util.List;
import java.util.Locale;
import net.elytrium.commons.velocity.commands.SuggestUtils;
import net.elytrium.limboauth.LimboAuth;
import net.elytrium.limboauth.Settings;
import net.elytrium.limboauth.data.Database;
import net.elytrium.limboauth.data.PlayerData;
import net.elytrium.limboauth.events.ChangePasswordEvent;
import net.elytrium.limboauth.serialization.ComponentSerializer;

public class ForceChangePasswordCommand implements SimpleCommand {

  private final LimboAuth plugin;

  public ForceChangePasswordCommand(LimboAuth plugin) {
    this.plugin = plugin;
  }

  @Override
  public void execute(SimpleCommand.Invocation invocation) {
    CommandSource source = invocation.source();
    String[] args = invocation.arguments();

    if (args.length == 2) {
      String nickname = args[0];
      String lowercaseNickname = nickname.toLowerCase(Locale.ROOT);
      String newPassword = args[1];

      Database database = this.plugin.getDatabase();
      database.select(PlayerData.Table.HASH_FIELD).from(PlayerData.Table.INSTANCE).where(PlayerData.Table.LOWERCASE_NICKNAME_FIELD.eq(lowercaseNickname)).fetchAsync().thenAccept(hashResult -> {
        if (hashResult.isEmpty()) {
          source.sendMessage(ComponentSerializer.replace(Settings.MESSAGES.forceChangePasswordNotRegistered, nickname));
          return;
        }

        final String oldHash = hashResult.get(0).value1();
        final String newHash = PlayerData.genHash(newPassword);

        database.update(PlayerData.Table.INSTANCE).set(PlayerData.Table.HASH_FIELD, newHash).where(PlayerData.Table.LOWERCASE_NICKNAME_FIELD.eq(lowercaseNickname)).executeAsync();

        this.plugin.getCacheManager().removePlayerFromCache(nickname);
        ProxyServer server = this.plugin.getServer();
        server.getPlayer(nickname).ifPresent(player -> player.sendMessage(ComponentSerializer.replace(Settings.MESSAGES.forceChangePasswordMessage, newPassword)));
        server.getEventManager().fireAndForget(new ChangePasswordEvent(nickname, null, oldHash, newPassword, newHash));
        source.sendMessage(ComponentSerializer.replace(Settings.MESSAGES.forceChangePasswordSuccessful, nickname));
      }).exceptionally(t -> {
        source.sendMessage(ComponentSerializer.replace(Settings.MESSAGES.forceChangePasswordNotSuccessful, nickname));
        return null;
      });
    } else {
      source.sendMessage(Settings.MESSAGES.forceChangePasswordUsage);
    }
  }

  @Override
  public List<String> suggest(SimpleCommand.Invocation invocation) {
    return SuggestUtils.suggestPlayers(this.plugin.getServer(), invocation.arguments(), 0);
  }

  @Override
  public boolean hasPermission(SimpleCommand.Invocation invocation) {
    return Settings.PERMISSION_STATES.forceChangePassword.hasPermission(invocation.source(), "limboauth.admin.forcechangepassword");
  }
}
