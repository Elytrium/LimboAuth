/*
 * Copyright (C) 2024 Elytrium
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

package net.elytrium.limboauth.command;

import com.velocitypowered.api.command.CommandManager;
import com.velocitypowered.api.command.CommandMeta;
import com.velocitypowered.api.command.RawCommand;
import java.util.List;
import java.util.concurrent.CompletableFuture;
import net.elytrium.fastutil.objects.Object2ObjectLinkedOpenHashMap;
import net.elytrium.fastutil.objects.Object2ObjectMap;
import net.elytrium.fastutil.objects.Object2ObjectMaps;
import net.elytrium.limboauth.LimboAuth;

public class AbstractRawCommand implements RawCommand { // TODO scope: player, console, both

  protected final LimboAuth plugin;

  private CommandMeta meta;

  private Object2ObjectMap<String, AbstractSubcommand> subcommands = Object2ObjectMaps.emptyMap();
  private String permission;
  private PermissionState permissionState;

  protected AbstractRawCommand(LimboAuth plugin) {
    this.plugin = plugin;
  }

  @Override
  public final void execute(RawCommand.Invocation invocation) {

  }

  @Override
  public CompletableFuture<List<String>> suggestAsync(RawCommand.Invocation invocation) {
    return null;
  }

  @Override
  public boolean hasPermission(RawCommand.Invocation invocation) {
    return this.permission == null || (this.permissionState == null ? invocation.source().hasPermission(this.permission) : this.permissionState.hasPermission(invocation.source(), this.permission));
  }

  public void register(List<String> aliases) {
    CommandManager commandManager = this.plugin.getServer().getCommandManager();
    if (this.meta != null) {
      commandManager.unregister(this.meta);
    }

    if (aliases.isEmpty()) {
      this.plugin.getLogger().info("Command {} wasn't registered due to lack of aliases", this.getClass().getName());
      this.meta = null;
    } else {
      CommandMeta.Builder builder = commandManager.metaBuilder(aliases.get(0));
      int amount = aliases.size();
      if (amount > 1) {
        builder.aliases(aliases.subList(1, amount).toArray(new String[amount - 1]));
      }

      commandManager.register(this.meta = builder.build(), this);
    }
  }

  public final AbstractRawCommand permission(String permission) {
    this.permission = permission;
    return this;
  }

  public final String permission() {
    return this.permission;
  }

  public final AbstractRawCommand permissionState(PermissionState permissionState) {
    this.permissionState = permissionState;
    return this;
  }

  public PermissionState permissionState() {
    return this.permissionState;
  }

  public final AbstractRawCommand subcommands(AbstractSubcommand... subcommands) {
    if (subcommands == null || subcommands.length == 0) {
      this.subcommands = Object2ObjectMaps.emptyMap();
    } else {
      if (this.subcommands == Object2ObjectMaps.EMPTY_MAP) {
        this.subcommands = new Object2ObjectLinkedOpenHashMap<>(subcommands.length);
      }

      for (AbstractSubcommand subcommand : subcommands) {
        for (String alias : subcommand.aliases) {
          this.subcommands.put(alias, subcommand);
        }
      }
    }

    return this;
  }

  public Object2ObjectMap<String, AbstractSubcommand> subcommands() {
    return this.subcommands;
  }

  private static int countArguments(String value) {
    int count = 0;
    int lastIndex = 0;
    while ((lastIndex = value.indexOf(' ', lastIndex)) != -1) {
      ++count;
      ++lastIndex;
    }

    return count;
  }
}
