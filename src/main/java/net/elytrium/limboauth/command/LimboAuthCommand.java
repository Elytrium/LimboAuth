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

import com.google.common.collect.ImmutableList;
import com.velocitypowered.api.command.CommandSource;
import com.velocitypowered.api.command.SimpleCommand;
import java.util.List;
import java.util.Map;
import java.util.stream.Collectors;
import net.elytrium.java.commons.mc.serialization.Serializer;
import net.elytrium.limboauth.LimboAuth;
import net.elytrium.limboauth.Settings;
import net.kyori.adventure.text.Component;
import net.kyori.adventure.text.format.NamedTextColor;

public class LimboAuthCommand implements SimpleCommand {

  private static final List<Component> HELP_MESSAGE = List.of(
      Component.text("This server is using LimboAuth and LimboAPI.", NamedTextColor.YELLOW),
      Component.text("(C) 2021 - 2023 Elytrium", NamedTextColor.YELLOW),
      Component.text("https://elytrium.net/github/", NamedTextColor.GREEN),
      Component.empty()
  );
  private static final Map<String, Component> SUBCOMMANDS = Map.of(
      /*
      "serverstats", Component.textOfChildren(
          Component.text("  /limboauth serverstats", NamedTextColor.GREEN),
          Component.text(" - ", NamedTextColor.DARK_GRAY),
          Component.text("Query for server stats.", NamedTextColor.YELLOW)
      ),
      "playerstats", Component.textOfChildren(
          Component.text("  /limboauth playerstats <player>", NamedTextColor.GREEN),
          Component.text(" - ", NamedTextColor.DARK_GRAY),
          Component.text("Query for stats of specified player.", NamedTextColor.YELLOW)
      ),
      */
      "reload", Component.textOfChildren(
          Component.text("  /limboauth reload", NamedTextColor.GREEN),
          Component.text(" - ", NamedTextColor.DARK_GRAY),
          Component.text("Reload config.", NamedTextColor.YELLOW)
      )
  );
  private static final Component AVAILABLE_SUBCOMMANDS_MESSAGE = Component.text("Available subcommands:", NamedTextColor.WHITE);
  private static final Component NO_AVAILABLE_SUBCOMMANDS_MESSAGE = Component.text("There is no available subcommands for you.", NamedTextColor.WHITE);

  private final LimboAuth plugin;

  public LimboAuthCommand(LimboAuth plugin) {
    this.plugin = plugin;
  }

  @Override
  public List<String> suggest(SimpleCommand.Invocation invocation) {
    CommandSource source = invocation.source();
    String[] args = invocation.arguments();

    if (args.length == 0) {
      return SUBCOMMANDS.keySet().stream()
          .filter(cmd -> source.hasPermission("limboauth.admin." + cmd))
          .collect(Collectors.toList());
    } else if (args.length == 1) {
      String argument = args[0];
      return SUBCOMMANDS.keySet().stream()
          .filter(cmd -> source.hasPermission("limboauth.admin." + cmd))
          .filter(str -> str.regionMatches(true, 0, argument, 0, argument.length()))
          .collect(Collectors.toList());
      /*
    } else if (args[0].equalsIgnoreCase("playerstats") && source.hasPermission("limboauth.admin.playerstats")) {
      return SuggestUtils.suggestPlayers(this.plugin.getServer(), args, 2);
      */
    } else {
      return ImmutableList.of();
    }
  }

  @Override
  public void execute(SimpleCommand.Invocation invocation) {
    CommandSource source = invocation.source();
    String[] args = invocation.arguments();

    int argsAmount = args.length;
    if (argsAmount > 0) {
      String command = args[0];
      Serializer serializer = LimboAuth.getSerializer();
      if (argsAmount == 1) {
        if (command.equalsIgnoreCase("reload") && source.hasPermission("limboauth.admin.reload")) {
          try {
            this.plugin.reload();
            source.sendMessage(serializer.deserialize(Settings.IMP.MAIN.STRINGS.RELOAD));
          } catch (Exception e) {
            e.printStackTrace();
            source.sendMessage(serializer.deserialize(Settings.IMP.MAIN.STRINGS.RELOAD_FAILED));
          }

          return;
        }
        /*
        else if (command.equalsIgnoreCase("serverstats") && source.hasPermission("limboauth.admin.serverstats")) {
          return;
        } else if (command.equalsIgnoreCase("playerstats") && source.hasPermission("limboauth.admin.playerstats")) {
          source.sendMessage(Component.text("Please specify a player."));
          return;
        }
        */
      }
      /*
      else if (argsAmount == 2) {
        if (command.equalsIgnoreCase("playerstats") && source.hasPermission("limboauth.admin.playerstats")) {
          RegisteredPlayer player = AuthSessionHandler.fetchInfo(this.plugin.getPlayerDao(), args[1]);
          if (player == null) {
            source.sendMessage(Component.text("Игрок даезент екзистс."));
          } else {
            source.sendMessage(Component.text("Стата геймера под ником {player}:"));
            source.sendMessage(Component.empty());
            source.sendMessage(Component.text("Ласт айпи: " + player.getIP()));
            source.sendMessage(Component.text("2fa: " + (player.getTotpToken().isEmpty() ? "Нет" : "Есть")));
          }

          return;
        }
      }
      */
    }

    this.showHelp(source);
  }

  private void showHelp(CommandSource source) {
    for (Component component : HELP_MESSAGE) {
      source.sendMessage(component);
    }
    List<Map.Entry<String, Component>> availableSubcommands = SUBCOMMANDS.entrySet().stream()
        .filter(command -> source.hasPermission("limboauth.admin." + command.getKey()))
        .collect(Collectors.toList());
    if (availableSubcommands.size() > 0) {
      source.sendMessage(AVAILABLE_SUBCOMMANDS_MESSAGE);
      availableSubcommands.forEach(command -> source.sendMessage(command.getValue()));
    } else {
      source.sendMessage(NO_AVAILABLE_SUBCOMMANDS_MESSAGE);
    }
  }
}
