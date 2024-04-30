/*
 * Copyright (C) 2021-2024 Elytrium
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

package net.elytrium.limboauth.commands;

import com.velocitypowered.api.command.CommandSource;
import com.velocitypowered.api.command.SimpleCommand;
import java.util.Arrays;
import java.util.List;
import java.util.Locale;
import java.util.function.Supplier;
import java.util.stream.Collectors;
import net.elytrium.limboauth.LimboAuth;
import net.elytrium.limboauth.Settings;
import net.elytrium.limboauth.utils.command.Commands;
import net.kyori.adventure.text.Component;
import net.kyori.adventure.text.format.NamedTextColor;

public class LimboAuthCommand implements SimpleCommand {

  private static final List<Component> HELP_MESSAGE = List.of(
      Component.text("This server is using LimboAuth and LimboAPI", NamedTextColor.YELLOW),
      Component.text("(C) 2021 - 2024 Elytrium", NamedTextColor.YELLOW),
      Component.text("https://elytrium.net/github/", NamedTextColor.GREEN),
      Component.empty()
  );

  private static final Component AVAILABLE_SUBCOMMANDS_MESSAGE = Component.text("Available subcommands:", NamedTextColor.WHITE);
  private static final Component NO_AVAILABLE_SUBCOMMANDS_MESSAGE = Component.text("There is no available subcommands for you", NamedTextColor.WHITE);

  private final LimboAuth plugin;

  public LimboAuthCommand(LimboAuth plugin) {
    this.plugin = plugin;

    Subcommand.RELOAD.setExecutor(new ReloadExecutor());
  }

  @Override
  public List<String> suggest(SimpleCommand.Invocation invocation) {
    CommandSource source = invocation.source();
    String[] args = invocation.arguments();

    if (args.length == 0) {
      return Arrays.stream(Subcommand.values())
          .filter(command -> command.hasPermission(source))
          .map(Subcommand::getCommand)
          .collect(Collectors.toList());
    } else if (args.length == 1) {
      String argument = args[0];
      return Arrays.stream(Subcommand.values())
          .filter(command -> command.hasPermission(source))
          .map(Subcommand::getCommand)
          .filter(str -> str.regionMatches(true, 0, argument, 0, argument.length()))
          .collect(Collectors.toList());
    } else {
      return List.of();
    }
  }

  @Override
  public void execute(SimpleCommand.Invocation invocation) {
    CommandSource source = invocation.source();
    String[] args = invocation.arguments();

    int argsAmount = args.length;
    if (argsAmount > 0) {
      try {
        Subcommand subcommand = Subcommand.valueOf(args[0].toUpperCase(Locale.ROOT));
        if (!subcommand.hasPermission(source)) {
          this.showHelp(source);
          return;
        }

        subcommand.executor.execute(this, source, args);
      } catch (IllegalArgumentException e) {
        this.showHelp(source);
      }
    } else {
      this.showHelp(source);
    }
  }

  @Override
  public boolean hasPermission(Invocation invocation) {
    return Settings.PERMISSION_STATES.help.hasPermission(invocation.source(), "limboauth.commands.help");
  }

  private void showHelp(CommandSource source) {
    HELP_MESSAGE.forEach(source::sendMessage);

    List<Subcommand> availableSubcommands = Arrays.stream(Subcommand.values())
        .filter(command -> command.hasPermission(source))
        .collect(Collectors.toList());

    if (!availableSubcommands.isEmpty()) {
      source.sendMessage(AVAILABLE_SUBCOMMANDS_MESSAGE);
      availableSubcommands.forEach(command -> source.sendMessage(command.getMessageLine()));
    } else {
      source.sendMessage(NO_AVAILABLE_SUBCOMMANDS_MESSAGE);
    }
  }

  private static class ReloadExecutor implements SubcommandExecutor {

    @Override
    public void execute(LimboAuthCommand parent, CommandSource source, String[] args) {
      parent.plugin.reload();
      source.sendMessage(Settings.MESSAGES.reload);
    }
  }

  private enum Subcommand {
    RELOAD("Reload config", () -> Settings.PERMISSION_STATES.reload);

    private final String command;
    private final String permission;
    private final Component messageLine;
    private final Supplier<Commands.PermissionState> permissionState;
    private SubcommandExecutor executor;

    Subcommand(String description, Supplier<Commands.PermissionState> permissionState) {
      this.permissionState = permissionState;
      this.command = this.name().toLowerCase(Locale.ROOT);
      this.permission = "limboauth.admin." + this.command;
      this.messageLine = Component.textOfChildren(
          Component.text("  /limboauth " + this.command, NamedTextColor.GREEN),
          Component.text(" - ", NamedTextColor.DARK_GRAY),
          Component.text(description, NamedTextColor.YELLOW)
      );
    }

    public void setExecutor(SubcommandExecutor executor) {
      this.executor = executor;
    }

    public boolean hasPermission(CommandSource source) {
      return this.permissionState.get().hasPermission(source, this.permission);
    }

    public Component getMessageLine() {
      return this.messageLine;
    }

    public String getCommand() {
      return this.command;
    }
  }

  private interface SubcommandExecutor {

    void execute(LimboAuthCommand parent, CommandSource source, String[] args);
  }
}
