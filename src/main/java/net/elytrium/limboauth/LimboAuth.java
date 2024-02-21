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

package net.elytrium.limboauth;

import com.google.inject.name.Named;
import com.velocitypowered.api.command.CommandManager;
import com.velocitypowered.api.network.ProtocolVersion;
import com.velocitypowered.api.plugin.PluginContainer;
import com.velocitypowered.api.plugin.annotation.DataDirectory;
import com.velocitypowered.api.proxy.Player;
import com.velocitypowered.api.proxy.ProxyServer;
import com.velocitypowered.api.proxy.messages.ChannelIdentifier;
import com.velocitypowered.api.proxy.messages.LegacyChannelIdentifier;
import com.velocitypowered.api.proxy.messages.MinecraftChannelIdentifier;
import java.io.IOException;
import java.nio.file.Path;
import java.util.List;
import java.util.concurrent.ExecutorService;
import net.elytrium.commons.utils.updates.UpdatesChecker;
import net.elytrium.limboapi.api.Limbo;
import net.elytrium.limboapi.api.LimboFactory;
import net.elytrium.limboapi.api.chunk.VirtualWorld;
import net.elytrium.limboapi.api.command.LimboCommandMeta;
import net.elytrium.limboauth.auth.AuthManager;
import net.elytrium.limboauth.auth.HybridAuthManager;
import net.elytrium.limboauth.cache.CacheManager;
import net.elytrium.limboauth.command.impl.ChangePasswordCommand;
import net.elytrium.limboauth.command.impl.DestroySessionCommand;
import net.elytrium.limboauth.command.impl.ForceChangePasswordCommand;
import net.elytrium.limboauth.command.impl.ForceRegisterCommand;
import net.elytrium.limboauth.command.impl.ForceUnregisterCommand;
import net.elytrium.limboauth.command.impl.LimboAuthCommand;
import net.elytrium.limboauth.command.impl.PremiumCommand;
import net.elytrium.limboauth.command.impl.TotpCommand;
import net.elytrium.limboauth.command.impl.UnregisterCommand;
import net.elytrium.limboauth.data.Database;
import net.elytrium.limboauth.data.PlayerData;
import net.elytrium.limboauth.events.AuthPluginReloadEvent;
import net.elytrium.limboauth.floodgate.FloodgateApiHolder;
import net.elytrium.limboauth.listener.AuthListener;
import net.elytrium.limboauth.password.UnsafePasswordManager;
import net.elytrium.limboauth.utils.Reflection;
import org.bstats.charts.SimplePie;
import org.bstats.charts.SingleLineChart;
import org.bstats.velocity.Metrics;
import org.slf4j.Logger;

public class LimboAuth {

  // Architectury API appends /541f59e4256a337ea252bc482a009d46 to the channel name, that is a UUID.nameUUIDFromBytes from the TokenMessage class name
  private static final ChannelIdentifier MOD_CHANNEL = MinecraftChannelIdentifier.create("limboauth", "mod/541f59e4256a337ea252bc482a009d46");
  private static final ChannelIdentifier LEGACY_MOD_CHANNEL = new LegacyChannelIdentifier("LIMBOAUTH|MOD");

  private final Logger logger;
  private final Path dataDirectory;
  private final Path configFile;
  private final ProxyServer server;
  private final ExecutorService executor;
  private final LimboFactory limboFactory;
  private final FloodgateApiHolder floodgateApi;

  private Database database;

  private Limbo authServer;
  private UnsafePasswordManager unsafePasswordManager;
  private CacheManager cacheManager;
  private HybridAuthManager hybridAuthManager;
  private AuthManager authManager;

  LimboAuth(Logger logger, @DataDirectory Path dataDirectory, ProxyServer server, ExecutorService executor, @Named("limboapi") PluginContainer limboApi) {
    this.logger = logger;
    this.dataDirectory = dataDirectory;
    this.configFile = dataDirectory.resolve("config.yml");
    this.server = server;
    this.executor = executor;
    this.limboFactory = (LimboFactory) limboApi.getInstance().orElseThrow();
    this.floodgateApi = this.server.getPluginManager().getPlugin("floodgate").isPresent() ? new FloodgateApiHolder() : null;
  }

  void onProxyInitialize() {
    try {
      this.reload();
    } catch (Throwable t) {
      this.logger.error("Caught unhandled exception", t);
      this.server.shutdown();
      return;
    }

    CommandManager commandManager = this.server.getCommandManager();
    // TODO config aliases
    commandManager.register("limboauth", new LimboAuthCommand(this), "la", "auth", "lauth");
    commandManager.register("unregister", new UnregisterCommand(this), "unreg");
    commandManager.register("forceregister", new ForceRegisterCommand(this), "forcereg");
    commandManager.register("premium", new PremiumCommand(this), "license");
    commandManager.register("forceunregister", new ForceUnregisterCommand(this), "forceunreg");
    commandManager.register("changepassword", new ChangePasswordCommand(this), "changepass", "cp");
    commandManager.register("forcechangepassword", new ForceChangePasswordCommand(this), "forcechangepass", "fcp");
    new DestroySessionCommand(this);
    if (Settings.HEAD.enableTotp) {
      commandManager.register("2fa", new TotpCommand(this), "totp");
    }

    this.server.getEventManager().register(this, new AuthListener(this, this.floodgateApi));

    try {
      final String previous = System.getProperty("bstats.relocatecheck");
      System.setProperty("bstats.relocatecheck", "false"); // Jokerge

      Metrics metrics = (Metrics) Reflection.findConstructor(Metrics.class, Object.class, ProxyServer.class, Logger.class, Path.class, int.class)
          .invokeExact(this, this.server, this.logger, this.dataDirectory, 13700);
      metrics.addCustomChart(new SimplePie("floodgate_auth", () -> String.valueOf(Settings.HEAD.floodgateNeedAuth)));
      metrics.addCustomChart(new SimplePie("premium_auth", () -> String.valueOf(Settings.HEAD.onlineModeNeedAuth)));
      metrics.addCustomChart(new SimplePie("db_type", () -> Settings.DATABASE.storageType.name()));
      metrics.addCustomChart(new SimplePie("load_world", () -> String.valueOf(Settings.HEAD.loadWorld)));
      metrics.addCustomChart(new SimplePie("totp_enabled", () -> String.valueOf(Settings.HEAD.enableTotp)));
      metrics.addCustomChart(new SimplePie("dimension", () -> Settings.HEAD.dimension.name()));
      metrics.addCustomChart(new SimplePie("save_uuid", () -> String.valueOf(Settings.HEAD.saveUuid)));
      metrics.addCustomChart(new SingleLineChart("registered_players", () -> this.database.fetchCount(PlayerData.Table.INSTANCE)));

      System.getProperty("bstats.relocatecheck", previous);
    } catch (Throwable e) {
      throw new RuntimeException(e);
    }

    try {
      if (!UpdatesChecker.checkVersionByURL("https://raw.githubusercontent.com/Elytrium/LimboAuth/master/VERSION", Settings.HEAD.version)) {
        this.logger.error("****************************************");
        this.logger.warn("The new LimboAuth update was found, please update");
        this.logger.error("https://github.com/Elytrium/LimboAuth/releases/");
        this.logger.error("****************************************");
      }
    } catch (Exception e) {
      this.logger.warn("Failed to check for updated!");
    }
  }

  void onProxyShutdown() {
    if (this.database != null) {
      this.database.close();
    }
  }

  public void reload() {
    Settings.HEAD.reload(this.configFile);

    if (this.floodgateApi == null && !Settings.HEAD.floodgateNeedAuth) {
      throw new IllegalStateException("If you want floodgate players to automatically pass auth (floodgate-need-auth: false), please install floodgate plugin");
    }

    try {
      if (this.database != null) {
        this.database.close();
      }

      this.database = new Database(this); // TODO dont reload
    } catch (Throwable e) {
      this.server.shutdown();
      throw new RuntimeException(e);
    }

    try {
      this.unsafePasswordManager = new UnsafePasswordManager(this);
    } catch (IOException e) {
      throw new RuntimeException(e);
    }

    this.cacheManager = new CacheManager();
    this.hybridAuthManager = new HybridAuthManager(this);
    this.authManager = new AuthManager(this);

    VirtualWorld authWorld = this.limboFactory.createVirtualWorld(
        Settings.HEAD.dimension,
        Settings.HEAD.authCoords.posX, Settings.HEAD.authCoords.posY, Settings.HEAD.authCoords.posZ,
        Settings.HEAD.authCoords.yaw, Settings.HEAD.authCoords.pitch
    );

    if (Settings.HEAD.loadWorld) {
      try {
        this.limboFactory
            .openWorldFile(Settings.HEAD.worldFileType, this.dataDirectory.resolve(Settings.HEAD.worldFilePath))
            .toWorld(this.limboFactory, authWorld, Settings.HEAD.worldCoords.posX, Settings.HEAD.worldCoords.posY, Settings.HEAD.worldCoords.posZ, Settings.HEAD.worldLightLevel);
      } catch (IOException e) {
        throw new IllegalArgumentException(e);
      }
    }

    if (this.authServer != null) {
      this.authServer.dispose();
    }

    this.authServer = this.limboFactory
        .createLimbo(authWorld)
        .setName("LimboAuth")
        .setWorldTime(Settings.HEAD.worldTicks)
        .setGameMode(Settings.HEAD.gameMode)
        .registerCommand(new LimboCommandMeta(LimboAuth.filterCommands(Settings.HEAD.registerCommand)))
        .registerCommand(new LimboCommandMeta(LimboAuth.filterCommands(Settings.HEAD.loginCommand)));

    if (Settings.HEAD.enableTotp) {
      this.authServer.registerCommand(new LimboCommandMeta(LimboAuth.filterCommands(Settings.HEAD.totpCommand)));
    }

    this.server.getEventManager().fireAndForget(new AuthPluginReloadEvent());
  }

  public ChannelIdentifier getChannelIdentifier(Player player) {
    return player.getProtocolVersion().noLessThan(ProtocolVersion.MINECRAFT_1_13) ? MOD_CHANNEL : LEGACY_MOD_CHANNEL;
  }

  public ProxyServer getServer() {
    return this.server;
  }

  public Limbo getAuthServer() {
    return this.authServer;
  }

  public Logger getLogger() {
    return this.logger;
  }

  public Path getDataDirectory() {
    return this.dataDirectory;
  }

  public ExecutorService getExecutor() {
    return this.executor;
  }

  public UnsafePasswordManager getUnsafePasswordManager() {
    return this.unsafePasswordManager;
  }

  public CacheManager getCacheManager() {
    return this.cacheManager;
  }

  public HybridAuthManager getHybridAuthManager() {
    return this.hybridAuthManager;
  }

  public AuthManager getAuthManager() {
    return this.authManager;
  }

  public LimboFactory getLimboFactory() {
    return this.limboFactory;
  }

  public FloodgateApiHolder getFloodgateApi() {
    return this.floodgateApi;
  }

  public Database getDatabase() {
    return this.database;
  }

  private static List<String> filterCommands(List<String> commands) {
    return commands.stream().map(command -> command.charAt(0) == '/' ? command.substring(1) : command).toList();
  }
}
