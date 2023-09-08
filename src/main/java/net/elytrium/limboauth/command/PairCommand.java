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
import net.elytrium.commons.kyori.serialization.Serializer;
import net.elytrium.limboauth.LimboAuth;
import net.elytrium.limboauth.Settings;
import net.elytrium.limboauth.model.RegisteredPlayer;
import net.kyori.adventure.text.Component;
import org.jooq.DSLContext;
import org.jooq.impl.DSL;

public class PairCommand implements SimpleCommand {

  private final LimboAuth plugin;
  private final Settings settings;
  private final DSLContext dslContext;

  private final String confirmKeyword;
  private final Component notRegistered;
  private final Component alreadyPremium;
  private final Component successful;
  private final Component errorOccurred;
  private final Component notPremium;
  private final Component wrongPassword;
  private final Component usage;
  private final Component notPlayer;

  public PairCommand(LimboAuth plugin, Settings settings, DSLContext dslContext) {
    this.plugin = plugin;
    this.settings = settings;
    this.dslContext = dslContext;

    Serializer serializer = plugin.getSerializer();
    this.confirmKeyword = this.settings.main.confirmKeyword;
    this.notRegistered = serializer.deserialize(this.settings.main.strings.NOT_REGISTERED);
    this.alreadyPremium = serializer.deserialize(this.settings.main.strings.ALREADY_PREMIUM);
    this.successful = serializer.deserialize(this.settings.main.strings.PREMIUM_SUCCESSFUL);
    this.errorOccurred = serializer.deserialize(this.settings.main.strings.ERROR_OCCURRED);
    this.notPremium = serializer.deserialize(this.settings.main.strings.NOT_PREMIUM);
    this.wrongPassword = serializer.deserialize(this.settings.main.strings.WRONG_PASSWORD);
    this.usage = serializer.deserialize(this.settings.main.strings.PREMIUM_USAGE);
    this.notPlayer = serializer.deserialize(this.settings.main.strings.NOT_PLAYER);
  }

  @Override
  public void execute(Invocation invocation) {
    CommandSource source = invocation.source();
    String[] args = invocation.arguments();

    if (source instanceof Player) {
      if (args.length == 2) {
        if (this.confirmKeyword.equalsIgnoreCase(args[1])) {
          String username = ((Player) source).getUsername();
          String lowercaseNickname = username.toLowerCase(Locale.ROOT);
          RegisteredPlayer.checkPassword(this.settings, this.dslContext, lowercaseNickname, args[0],
              () -> source.sendMessage(this.notRegistered),
              () -> source.sendMessage(this.alreadyPremium),
              h -> this.plugin.isPremiumExternal(lowercaseNickname).thenAccept(premiumResponse -> {
                if (premiumResponse.getState() == LimboAuth.PremiumState.PREMIUM_USERNAME) {
                  this.dslContext.update(RegisteredPlayer.Table.INSTANCE)
                      .set(RegisteredPlayer.Table.HASH_FIELD, "")
                      .where(DSL.field(RegisteredPlayer.Table.LOWERCASE_NICKNAME_FIELD).eq(lowercaseNickname))
                      .executeAsync()
                      .thenRun(() -> {
                        this.plugin.removePlayerFromCache(username);
                        ((Player) source).disconnect(this.successful);
                      }).exceptionally(e -> {
                        this.plugin.handleSqlError(e);
                        source.sendMessage(this.errorOccurred);
                        return null;
                      });
                } else {
                  source.sendMessage(this.notPremium);
                }
              }),
              () -> source.sendMessage(this.wrongPassword),
              e -> source.sendMessage(this.errorOccurred));

          return;
        }
      }

      source.sendMessage(this.usage);
    } else {
      source.sendMessage(this.notPlayer);
    }
  }

  @Override
  public boolean hasPermission(Invocation invocation) {
    return this.settings.main.commandPermissionState.premium
        .hasPermission(invocation.source(), "limboauth.commands.premium");
  }
}
