package net.elytrium.limboauth.command;

import com.velocitypowered.api.command.RawCommand;
import net.elytrium.fastutil.objects.Object2ObjectLinkedOpenHashMap;
import net.elytrium.fastutil.objects.Object2ObjectMap;
import net.elytrium.fastutil.objects.Object2ObjectMaps;
import net.elytrium.limboauth.LimboAuth;

public class BaseRawCommand implements RawCommand {

  protected final LimboAuth plugin;

  protected Object2ObjectMap<String, BaseSubcommand> subcommands = Object2ObjectMaps.emptyMap();
  private String permission;
  private PermissionState permissionState;

  protected BaseRawCommand(LimboAuth plugin, String alias, String... aliases) {
    this.plugin = plugin;
    plugin.getServer().getCommandManager().register(alias, this, aliases);
  }

  @Override
  public void execute(RawCommand.Invocation invocation) {

  }

  @Override
  public boolean hasPermission(RawCommand.Invocation invocation) {
    return this.permission == null || (this.permissionState == null ? invocation.source().hasPermission(this.permission) : this.permissionState.hasPermission(invocation.source(), this.permission));
  }

  public final BaseRawCommand permission(String permission) {
    this.permission = permission;
    return this;
  }

  public final BaseRawCommand permissionState(PermissionState permissionState) {
    this.permissionState = permissionState;
    return this;
  }

  public final String permission() {
    return this.permission;
  }

  public final BaseRawCommand subcommands(BaseSubcommand... subcommands) {
    if (subcommands == null || subcommands.length == 0) {
      this.subcommands = Object2ObjectMaps.emptyMap();
    } else {
      this.subcommands = new Object2ObjectLinkedOpenHashMap<>(subcommands.length);
      for (BaseSubcommand subcommand : subcommands) {
        for (String alias : subcommand.aliases) {
          this.subcommands.put(alias, subcommand);
        }
      }
    }

    return this;
  }

  public Object2ObjectMap<String, BaseSubcommand> subcommands() {
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
