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

package net.elytrium.limboauth;

import com.google.inject.name.Named;
import com.velocitypowered.api.command.CommandManager;
import com.velocitypowered.api.event.EventManager;
import com.velocitypowered.api.network.ProtocolVersion;
import com.velocitypowered.api.plugin.PluginContainer;
import com.velocitypowered.api.plugin.annotation.DataDirectory;
import com.velocitypowered.api.proxy.Player;
import com.velocitypowered.api.proxy.ProxyServer;
import com.velocitypowered.api.proxy.messages.ChannelIdentifier;
import com.velocitypowered.api.proxy.messages.LegacyChannelIdentifier;
import com.velocitypowered.api.proxy.messages.MinecraftChannelIdentifier;
import java.io.IOException;
import java.net.InetAddress;
import java.nio.charset.StandardCharsets;
import java.nio.file.Files;
import java.nio.file.Path;
import java.util.ArrayDeque;
import java.util.Collections;
import java.util.HashSet;
import java.util.List;
import java.util.Objects;
import java.util.Queue;
import java.util.Set;
import java.util.UUID;
import java.util.concurrent.CompletableFuture;
import java.util.concurrent.ConcurrentHashMap;
import java.util.concurrent.ExecutionException;
import java.util.concurrent.ExecutorService;
import java.util.stream.Collectors;
import net.elytrium.commons.kyori.serialization.Serializer;
import net.elytrium.commons.kyori.serialization.Serializers;
import net.elytrium.commons.utils.updates.UpdatesChecker;
import net.elytrium.limboapi.api.Limbo;
import net.elytrium.limboapi.api.LimboFactory;
import net.elytrium.limboapi.api.chunk.VirtualWorld;
import net.elytrium.limboapi.api.command.LimboCommandMeta;
import net.elytrium.limboapi.api.file.WorldFile;
import net.elytrium.limboauth.command.ChangePasswordCommand;
import net.elytrium.limboauth.command.DestroySessionCommand;
import net.elytrium.limboauth.command.ForceChangePasswordCommand;
import net.elytrium.limboauth.command.ForceRegisterCommand;
import net.elytrium.limboauth.command.ForceUnregisterCommand;
import net.elytrium.limboauth.command.LimboAuthCommand;
import net.elytrium.limboauth.command.PremiumCommand;
import net.elytrium.limboauth.command.TotpCommand;
import net.elytrium.limboauth.command.UnregisterCommand;
import net.elytrium.limboauth.data.Database;
import net.elytrium.limboauth.events.AuthPluginReloadEvent;
import net.elytrium.limboauth.floodgate.FloodgateApiHolder;
import net.elytrium.limboauth.auth.HybridAuthManager;
import net.elytrium.limboauth.listener.AuthListener;
import net.elytrium.limboauth.data.PlayerData;
import net.kyori.adventure.text.Component;
import net.kyori.adventure.text.serializer.ComponentSerializer;
import org.bstats.charts.SimplePie;
import org.bstats.charts.SingleLineChart;
import org.bstats.velocity.Metrics;
import org.jooq.DSLContext;
import org.jooq.Field;
import org.jooq.Table;
import org.jooq.impl.DSL;
import org.slf4j.Logger;

public class LimboAuth { // split one bing class into small ones (главный гласс должен служить бутстраппером для остальных систем, а не главным обработчиком этих систем)

  // Architectury API appends /541f59e4256a337ea252bc482a009d46 to the channel name, that is a UUID.nameUUIDFromBytes from the TokenMessage class name
  private static final ChannelIdentifier MOD_CHANNEL = MinecraftChannelIdentifier.create("limboauth", "mod/541f59e4256a337ea252bc482a009d46");
  private static final ChannelIdentifier LEGACY_MOD_CHANNEL = new LegacyChannelIdentifier("LIMBOAUTH|MOD");

  // TODO поменять все карты, сэты и листы с очередями на fastutil
  private final Map<String, CachedSessionUser> cachedAuthChecks = new ConcurrentHashMap<>();
  private final Map<String, CachedPremiumUser> premiumCache = new ConcurrentHashMap<>();
  private final Map<InetAddress, CachedBruteforceUser> bruteforceCache = new ConcurrentHashMap<>();
  private final Map<UUID, Runnable> postLoginTasks = new ConcurrentHashMap<>();
  private final Set<String> unsafePasswords = new HashSet<>();
  private final Set<String> forcedPreviously = Collections.synchronizedSet(new HashSet<>());

  private final Logger logger;
  private final Path dataDirectory;
  private final Path configFile;
  private final ProxyServer server;
  private final ExecutorService executor;
  private final Metrics.Factory metricsFactory;
  private final LimboFactory limboFactory;
  private final FloodgateApiHolder floodgateApi;

  private Database database;
  private Serializer serializer;

  private ScheduledTask purgeCacheTask;
  private ScheduledTask purgePremiumCacheTask;
  private ScheduledTask purgeBruteforceCacheTask;

  private Limbo authServer;

  LimboAuth(Logger logger, @DataDirectory Path dataDirectory, ProxyServer server, Metrics.Factory metricsFactory, ExecutorService executor, @Named("limboapi") PluginContainer limboApi) {
    this.logger = logger;
    this.dataDirectory = dataDirectory;
    this.configFile = dataDirectory.resolve("config.yml");
    this.server = server;
    this.metricsFactory = metricsFactory; // TODO создавать рефлексией видимо
    this.executor = executor;
    this.limboFactory = (LimboFactory) limboApi.getInstance().orElseThrow();
    this.floodgateApi = this.server.getPluginManager().getPlugin("floodgate").isPresent() ? new FloodgateApiHolder() : null;
  }

  void onProxyInitialization() {
    try {
      this.reload();
    } catch (Throwable t) {
      this.logger.error("Caught unhandled exception", t);
      this.server.shutdown();
      return;
    }

    CommandManager manager = this.server.getCommandManager();
    manager.register("unregister", new UnregisterCommand(this), "unreg");
    manager.register("forceregister", new ForceRegisterCommand(this), "forcereg");
    manager.register("premium", new PremiumCommand(this), "license");
    manager.register("forceunregister", new ForceUnregisterCommand(this), "forceunreg");
    manager.register("changepassword", new ChangePasswordCommand(this), "changepass", "cp");
    manager.register("forcechangepassword", new ForceChangePasswordCommand(this), "forcechangepass", "fcp");
    manager.register("destroysession", new DestroySessionCommand(this), "logout");
    if (Settings.HEAD.enableTotp) {
      manager.register("2fa", new TotpCommand(this), "totp");
    }
    manager.register("limboauth", new LimboAuthCommand(this), "la", "auth", "lauth");

    Metrics metrics = this.metricsFactory.make(this, 13700);
    metrics.addCustomChart(new SimplePie("floodgate_auth", () -> String.valueOf(Settings.HEAD.floodgateNeedAuth)));
    metrics.addCustomChart(new SimplePie("premium_auth", () -> String.valueOf(Settings.HEAD.onlineModeNeedAuth)));
    metrics.addCustomChart(new SimplePie("db_type", () -> String.valueOf(Settings.DATABASE.storageType)));
    metrics.addCustomChart(new SimplePie("load_world", () -> String.valueOf(Settings.HEAD.loadWorld)));
    metrics.addCustomChart(new SimplePie("totp_enabled", () -> String.valueOf(Settings.HEAD.enableTotp)));
    metrics.addCustomChart(new SimplePie("dimension", () -> String.valueOf(Settings.HEAD.dimension)));
    metrics.addCustomChart(new SimplePie("save_uuid", () -> String.valueOf(Settings.HEAD.saveUuid)));
    metrics.addCustomChart(new SingleLineChart("registered_players", () -> Math.toIntExact(this.database.getContext().fetchCount(PlayerData.Table.INSTANCE))));

    try {
      if (!UpdatesChecker.checkVersionByURL("https://raw.githubusercontent.com/Elytrium/LimboAuth/master/VERSION", Settings.HEAD.version)) {
        this.logger.error("****************************************");
        this.logger.warn("The new LimboAuth update was found, please update.");
        this.logger.error("https://github.com/Elytrium/LimboAuth/releases/");
        this.logger.error("****************************************");
      }
    } catch (Exception e) {
      this.logger.warn("Failed to check for updated!");
    }
  }

  void onProxyShutdown() {
    if (this.database != null) {
      this.database.shutdown();
    }
  }

  @SuppressFBWarnings(value = "NP_NULL_ON_SOME_PATH", justification = "LEGACY_AMPERSAND can't be null in velocity.")
  public void reload() {
    Settings.HEAD.reload(this.configFile);

    if (this.floodgateApi == null && !Settings.HEAD.floodgateNeedAuth) {
      throw new IllegalStateException("If you want floodgate players to automatically pass auth (floodgate-need-auth: false), please install floodgate plugin.");
    }

    try {
      this.database = new Database(this);
    } catch (Throwable e) {
      throw new RuntimeException(e);
    }

    ComponentSerializer<Component, Component, String> serializer = Settings.HEAD.serializer.getSerializer();
    if (serializer == null) {
      this.logger.warn("The specified serializer could not be founded, using default. (LEGACY_AMPERSAND)");
      this.serializer = new Serializer(Objects.requireNonNull(Serializers.LEGACY_AMPERSAND.getSerializer()));
    } else {
      this.serializer = new Serializer(serializer);
    }

    if (Settings.HEAD.checkPasswordStrength) {
      try {
        this.unsafePasswords.clear();
        Path unsafePasswordsPath = this.dataDirectory.resolve(Settings.HEAD.unsafePasswordsFile);
        if (!unsafePasswordsPath.toFile().exists()) {
          Files.copy(Objects.requireNonNull(this.getClass().getResourceAsStream("/unsafe_passwords.txt")), unsafePasswordsPath);
        }

        this.unsafePasswords.addAll(Files.readAllLines(unsafePasswordsPath));
      } catch (IOException e) {
        throw new IllegalArgumentException(e);
      }
    }

    this.cacheManager = new CacheManager();
    this.hybridAuthManager = new HybridAuthManager(this);
    this.authManager = new AuthManager(this);

    VirtualWorld authWorld = this.limboFactory.createVirtualWorld(
        Settings.HEAD.dimension,
        Settings.HEAD.authCoords.posX, Settings.HEAD.authCoords.posY, Settings.HEAD.authCoords.posZ,
        (float) Settings.HEAD.authCoords.yaw, (float) Settings.HEAD.authCoords.pitch
    );

    if (Settings.HEAD.loadWorld) {
      try {
        this.limboFactory.openWorldFile(Settings.HEAD.worldFileType, this.dataDirectory.resolve(Settings.HEAD.worldFilePath)).toWorld(
            this.limboFactory, authWorld, Settings.HEAD.worldCoords.posX, Settings.HEAD.worldCoords.posY, Settings.HEAD.worldCoords.posZ, Settings.HEAD.worldLightLevel
        );
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
        .registerCommand(new LimboCommandMeta(this.filterCommands(Settings.HEAD.registerCommand)))
        .registerCommand(new LimboCommandMeta(this.filterCommands(Settings.HEAD.loginCommand)));

    if (Settings.HEAD.enableTotp) {
      this.authServer.registerCommand(new LimboCommandMeta(this.filterCommands(Settings.HEAD.totpCommand)));
    }

    EventManager eventManager = this.server.getEventManager();
    eventManager.unregisterListeners(this);
    eventManager.register(this, new AuthListener(this, this.floodgateApi));

    eventManager.fireAndForget(new AuthPluginReloadEvent());
  }

  private List<String> filterCommands(List<String> commands) {
    return commands.stream().filter(command -> command.startsWith("/")).map(command -> command.substring(1)).collect(Collectors.toList());
  }

  public ChannelIdentifier getChannelIdentifier(Player player) {
    return player.getProtocolVersion().compareTo(ProtocolVersion.MINECRAFT_1_13) >= 0 ? MOD_CHANNEL : LEGACY_MOD_CHANNEL;
  }

  public Set<String> getUnsafePasswords() {
    return this.unsafePasswords;
  }

  public ProxyServer getServer() {
    return this.server;
  }

  public DSLContext getDslContext() {
    return this.dslContext;
  }

  public Serializer getSerializer() {
    return this.serializer;
  }

  public Limbo getAuthServer() {
    return this.authServer;
  }

  public Pattern getNicknameValidationPattern() {
    return this.nicknameValidationPattern;
  }

  public Logger getLogger() {
    return this.logger;
  }

  public <T> T handleSqlError(Throwable t) {
    this.logger.error("An unexpected internal error was caught during the database SQL operations.", t);
    return null;
  }

  private static class CachedUser {

    private final long checkTime;

    public CachedUser(long checkTime) {
      this.checkTime = checkTime;
    }

    public long getCheckTime() {
      return this.checkTime;
    }
  }

  private static class CachedSessionUser extends CachedUser {

    private final InetAddress inetAddress;
    private final String username;

    public CachedSessionUser(long checkTime, InetAddress inetAddress, String username) {
      super(checkTime);

      this.inetAddress = inetAddress;
      this.username = username;
    }

    public InetAddress getInetAddress() {
      return this.inetAddress;
    }

    public String getUsername() {
      return this.username;
    }
  }

  private static class CachedPremiumUser extends CachedUser {

    private final boolean premium;

    public CachedPremiumUser(long checkTime, boolean premium) {
      super(checkTime);

      this.premium = premium;
    }

    public boolean isPremium() {
      return this.premium;
    }
  }

  private static class CachedBruteforceUser extends CachedUser {

    private int attempts;

    public CachedBruteforceUser(long checkTime) {
      super(checkTime);
    }

    public void incrementAttempts() {
      this.attempts++;
    }

    public int getAttempts() {
      return this.attempts;
    }
  }

  public static class PremiumResponse {

    public static final PremiumResponse CRACKED = new PremiumResponse(PremiumState.CRACKED);
    public static final PremiumResponse PREMIUM = new PremiumResponse(PremiumState.PREMIUM);
    public static final PremiumResponse UNKNOWN = new PremiumResponse(PremiumState.UNKNOWN);
    public static final PremiumResponse RATE_LIMIT = new PremiumResponse(PremiumState.RATE_LIMIT);
    public static final PremiumResponse ERROR = new PremiumResponse(PremiumState.ERROR);

    private final PremiumState state;
    private final UUID uuid;

    public PremiumResponse(PremiumState state) {
      this.state = state;
      this.uuid = null;
    }

    public PremiumResponse(PremiumState state, UUID uuid) {
      this.state = state;
      this.uuid = uuid;
    }

    public PremiumResponse(PremiumState state, String uuid) {
      this.state = state;
      if (uuid.contains("-")) {
        this.uuid = UUID.fromString(uuid);
      } else {
        this.uuid = new UUID(Long.parseUnsignedLong(uuid.substring(0, 16), 16), Long.parseUnsignedLong(uuid.substring(16), 16));
      }
    }

    public PremiumState getState() {
      return this.state;
    }

    public UUID getUuid() {
      return this.uuid;
    }
  }

  public enum PremiumState {
    PREMIUM,
    PREMIUM_USERNAME,
    CRACKED,
    UNKNOWN,
    RATE_LIMIT,
    ERROR
  }

  public interface PremiumCheckStep {

    void checkIsPremiumAndCacheStep(Function<String, CompletableFuture<PremiumResponse>> function, String nickname, String lowercaseNickname,
        boolean premium, boolean unknown, boolean wasRateLimited, boolean wasError, UUID uuid);
  }
}
