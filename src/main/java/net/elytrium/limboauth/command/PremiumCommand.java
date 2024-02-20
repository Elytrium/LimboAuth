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

import com.velocitypowered.api.command.CommandSource;
import com.velocitypowered.api.command.SimpleCommand;
import com.velocitypowered.api.proxy.Player;
import net.elytrium.commons.kyori.serialization.Serializer;
import net.elytrium.limboauth.LimboAuth;
import net.elytrium.limboauth.Settings;
import net.elytrium.limboauth.model.RegisteredPlayer;
import net.elytrium.limboauth.storage.PlayerStorage;
import net.elytrium.limboauth.util.CryptUtils;
import net.kyori.adventure.text.Component;

public class PremiumCommand extends RatelimitedCommand {

  private final LimboAuth plugin;
  private final PlayerStorage playerStorage;

  private final String confirmKeyword;
  private final Component notRegistered;
  private final Component alreadyPremium;
  private final Component successful;
  private final Component errorOccurred;
  private final Component notPremium;
  private final Component wrongPassword;
  private final Component usage;
  private final Component notPlayer;

  public PremiumCommand(LimboAuth plugin, PlayerStorage playerStorage) {
    this.plugin = plugin;
    this.playerStorage = playerStorage;

    Serializer serializer = LimboAuth.getSerializer();
    this.confirmKeyword = Settings.IMP.MAIN.CONFIRM_KEYWORD;
    this.notRegistered = serializer.deserialize(Settings.IMP.MAIN.STRINGS.NOT_REGISTERED);
    this.alreadyPremium = serializer.deserialize(Settings.IMP.MAIN.STRINGS.ALREADY_PREMIUM);
    this.successful = serializer.deserialize(Settings.IMP.MAIN.STRINGS.PREMIUM_SUCCESSFUL);
    this.errorOccurred = serializer.deserialize(Settings.IMP.MAIN.STRINGS.ERROR_OCCURRED);
    this.notPremium = serializer.deserialize(Settings.IMP.MAIN.STRINGS.NOT_PREMIUM);
    this.wrongPassword = serializer.deserialize(Settings.IMP.MAIN.STRINGS.WRONG_PASSWORD);
    this.usage = serializer.deserialize(Settings.IMP.MAIN.STRINGS.PREMIUM_USAGE);
    this.notPlayer = serializer.deserialize(Settings.IMP.MAIN.STRINGS.NOT_PLAYER);
  }

  @Override
  public void execute(CommandSource source, String[] args) {
    if (source instanceof Player) {
      if (args.length == 2) {
        if (this.confirmKeyword.equalsIgnoreCase(args[1])) {
          String username = ((Player) source).getUsername();
          String usernameLowercase = username.toLowerCase();

          RegisteredPlayer player = this.playerStorage.getAccount(username);

          if (player == null) {
            source.sendMessage(this.notRegistered);
          } else if (player.getHash().isEmpty()) {
            source.sendMessage(this.alreadyPremium);
          } else if (CryptUtils.checkPassword(args[0], player)) {
            if (this.plugin.isPremiumExternal(usernameLowercase).getState() == LimboAuth.PremiumState.PREMIUM_USERNAME) {
              player.setHash("");
              ((Player) source).disconnect(this.successful);
            } else {
              source.sendMessage(this.notPremium);
            }
          } else {
            source.sendMessage(this.wrongPassword);
          }

          return;
        }
      }

      source.sendMessage(this.usage);
    } else {
      source.sendMessage(this.notPlayer);
    }
  }

  @Override
  public boolean hasPermission(SimpleCommand.Invocation invocation) {
    return Settings.IMP.MAIN.COMMAND_PERMISSION_STATE.PREMIUM
        .hasPermission(invocation.source(), "limboauth.commands.premium");
  }
}
