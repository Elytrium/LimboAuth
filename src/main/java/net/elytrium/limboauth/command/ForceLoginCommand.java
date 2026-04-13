/*
 * Copyright (C) 2021 - 2025 Elytrium
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
import java.text.MessageFormat;
import java.util.ArrayList;
import java.util.List;
import net.elytrium.commons.kyori.serialization.Serializer;
import net.elytrium.limboauth.LimboAuth;
import net.elytrium.limboauth.Settings;
import net.elytrium.limboauth.handler.AuthSessionHandler;
import net.kyori.adventure.text.Component;

public class ForceLoginCommand extends RatelimitedCommand {

  private final LimboAuth plugin;

  private final String successful;
  private final String unknownPlayer;
  private final Component usage;

  public ForceLoginCommand(LimboAuth plugin) {
    this.plugin = plugin;

    this.successful = Settings.IMP.MAIN.STRINGS.FORCE_LOGIN_SUCCESSFUL;
    this.unknownPlayer = Settings.IMP.MAIN.STRINGS.FORCE_LOGIN_UNKNOWN_PLAYER;
    this.usage = LimboAuth.getSerializer().deserialize(Settings.IMP.MAIN.STRINGS.FORCE_LOGIN_USAGE);
  }

  @Override
  public void execute(CommandSource source, String[] args) {
    if (args.length == 1) {
      String nickname = args[0];

      Serializer serializer = LimboAuth.getSerializer();
      AuthSessionHandler handler = this.plugin.getAuthenticatingPlayer(nickname);
      if (handler == null) {
        source.sendMessage(serializer.deserialize(MessageFormat.format(this.unknownPlayer, nickname)));
        return;
      }

      handler.finishLogin();
      source.sendMessage(serializer.deserialize(MessageFormat.format(this.successful, nickname)));
    } else {
      source.sendMessage(this.usage);
    }
  }

  @Override
  public boolean hasPermission(Invocation invocation) {
    return Settings.IMP.MAIN.COMMAND_PERMISSION_STATE.FORCE_LOGIN
        .hasPermission(invocation.source(), "limboauth.admin.forcelogin");
  }

  @Override
  public List<String> suggest(Invocation invocation) {
    if (invocation.arguments().length > 1) {
      return super.suggest(invocation);
    }

    String nickname = invocation.arguments().length == 0 ? "" : invocation.arguments()[0];
    List<String> suggest = new ArrayList<>();
    for (String username : this.plugin.getAuthenticatingPlayers().keySet()) {
      if (username.startsWith(nickname)) {
        suggest.add(username);
      }
    }

    return suggest;
  }
}
