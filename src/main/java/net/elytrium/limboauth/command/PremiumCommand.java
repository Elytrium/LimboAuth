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
import net.elytrium.limboauth.LimboAuth;
import net.elytrium.limboauth.Settings;
import net.elytrium.limboauth.data.Database;
import net.elytrium.limboauth.data.PlayerData;

public class PremiumCommand implements SimpleCommand {

  private final LimboAuth plugin;

  public PremiumCommand(LimboAuth plugin) {
    this.plugin = plugin;
  }

  @Override
  public void execute(SimpleCommand.Invocation invocation) {
    CommandSource source = invocation.source();
    String[] args = invocation.arguments();

    if (source instanceof Player player) {
      if (args.length == 2) {
        if (Settings.HEAD.confirmKeyword.equalsIgnoreCase(args[1])) {
          String username = ((Player) source).getUsername();
          String lowercaseNickname = username.toLowerCase(Locale.ROOT);
          PlayerData.checkPassword(lowercaseNickname, args[0],
              () -> source.sendMessage(Settings.MESSAGES.notRegistered),
              () -> source.sendMessage(Settings.MESSAGES.alreadyPremium),
              h -> this.plugin.isPremiumExternal(lowercaseNickname).thenAccept(premiumResponse -> {
                if (premiumResponse.getState() == LimboAuth.PremiumState.PREMIUM_USERNAME) {
                  this.plugin.getDatabase().update(PlayerData.Table.INSTANCE)
                      .set(PlayerData.Table.HASH_FIELD, "")
                      .where(PlayerData.Table.LOWERCASE_NICKNAME_FIELD.eq(lowercaseNickname))
                      .executeAsync()
                      .thenRun(() -> {
                        this.plugin.removePlayerFromCache(username);
                        player.disconnect(Settings.MESSAGES.premiumSuccessful);
                      }).exceptionally(t -> {
                        source.sendMessage(Settings.MESSAGES.errorOccurred);
                        return null;
                      });
                } else {
                  source.sendMessage(Settings.MESSAGES.notPremium);
                }
              }),
              () -> source.sendMessage(Settings.MESSAGES.wrongPassword),
              e -> source.sendMessage(Settings.MESSAGES.errorOccurred)
          );

          return;
        }
      }

      source.sendMessage(Settings.MESSAGES.premiumUsage);
    } else {
      source.sendMessage(Settings.MESSAGES.notPlayer);
    }
  }

  @Override
  public boolean hasPermission(SimpleCommand.Invocation invocation) {
    return Settings.HEAD.commandPermissionState.premium.hasPermission(invocation.source(), "limboauth.commands.premium");
  }
}
