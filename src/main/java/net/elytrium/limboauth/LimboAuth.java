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

import com.google.common.net.UrlEscapers;
import com.google.common.primitives.Bytes;
import com.google.common.primitives.Longs;
import com.google.gson.JsonElement;
import com.google.gson.JsonObject;
import com.google.gson.JsonParser;
import com.google.inject.Inject;
import com.velocitypowered.api.command.CommandManager;
import com.velocitypowered.api.event.EventManager;
import com.velocitypowered.api.event.Subscribe;
import com.velocitypowered.api.event.proxy.ProxyInitializeEvent;
import com.velocitypowered.api.network.ProtocolVersion;
import com.velocitypowered.api.plugin.Dependency;
import com.velocitypowered.api.plugin.Plugin;
import com.velocitypowered.api.plugin.PluginContainer;
import com.velocitypowered.api.plugin.annotation.DataDirectory;
import com.velocitypowered.api.proxy.Player;
import com.velocitypowered.api.proxy.ProxyServer;
import com.velocitypowered.api.proxy.messages.ChannelIdentifier;
import com.velocitypowered.api.proxy.messages.LegacyChannelIdentifier;
import com.velocitypowered.api.proxy.messages.MinecraftChannelIdentifier;
import com.velocitypowered.api.scheduler.ScheduledTask;
import com.velocitypowered.proxy.VelocityServer;
import edu.umd.cs.findbugs.annotations.SuppressFBWarnings;
import io.whitfin.siphash.SipHasher;
import java.io.File;
import java.io.IOException;
import java.net.InetAddress;
import java.net.http.HttpClient;
import java.nio.charset.StandardCharsets;
import java.nio.file.Files;
import java.nio.file.Path;
import java.nio.file.Paths;
import java.sql.SQLException;
import java.util.ArrayDeque;
import java.util.Collections;
import java.util.HashSet;
import java.util.List;
import java.util.Locale;
import java.util.Map;
import java.util.Objects;
import java.util.Queue;
import java.util.Set;
import java.util.UUID;
import java.util.concurrent.CompletableFuture;
import java.util.concurrent.ConcurrentHashMap;
import java.util.concurrent.ExecutionException;
import java.util.concurrent.ExecutorService;
import java.util.concurrent.TimeUnit;
import java.util.function.Consumer;
import java.util.function.Function;
import java.util.regex.Pattern;
import java.util.stream.Collectors;
import java.util.stream.Stream;
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
import net.elytrium.limboauth.dependencies.DatabaseLibrary;
import net.elytrium.limboauth.event.AuthPluginReloadEvent;
import net.elytrium.limboauth.event.PreAuthorizationEvent;
import net.elytrium.limboauth.event.PreEvent;
import net.elytrium.limboauth.event.PreRegisterEvent;
import net.elytrium.limboauth.event.TaskEvent;
import net.elytrium.limboauth.floodgate.FloodgateApiHolder;
import net.elytrium.limboauth.handler.AuthSessionHandler;
import net.elytrium.limboauth.listener.AuthListener;
import net.elytrium.limboauth.model.RegisteredPlayer;
import net.elytrium.limboauth.model.SQLRuntimeException;
import net.kyori.adventure.text.Component;
import net.kyori.adventure.text.serializer.ComponentSerializer;
import net.kyori.adventure.title.Title;
import org.asynchttpclient.AsyncHttpClient;
import org.asynchttpclient.ListenableFuture;
import org.asynchttpclient.Response;
import org.bstats.charts.SimplePie;
import org.bstats.charts.SingleLineChart;
import org.bstats.velocity.Metrics;
import org.checkerframework.checker.nullness.qual.MonotonicNonNull;
import org.checkerframework.checker.nullness.qual.Nullable;
import org.jooq.DSLContext;
import org.jooq.Field;
import org.jooq.Table;
import org.jooq.impl.DSL;
import org.slf4j.Logger;

@Plugin(
    id = "limboauth",
    name = "LimboAuth",
    version = BuildConstants.AUTH_VERSION,
    url = "https://elytrium.net/",
    authors = {
        "Elytrium (https://elytrium.net/)",
    },
    dependencies = {
        @Dependency(id = "limboapi"),
        @Dependency(id = "floodgate", optional = true)
    }
)
public class LimboAuth {

  // Architectury API appends /541f59e4256a337ea252bc482a009d46 to the channel name, that is a UUID.nameUUIDFromBytes from the TokenMessage class name
  private static final ChannelIdentifier MOD_CHANNEL = MinecraftChannelIdentifier.create("limboauth", "mod/541f59e4256a337ea252bc482a009d46");
  private static final ChannelIdentifier LEGACY_MOD_CHANNEL = new LegacyChannelIdentifier("LIMBOAUTH|MOD");

  @MonotonicNonNull
  private static Logger LOGGER;
  @MonotonicNonNull
  private static Serializer SERIALIZER;

  private final Map<String, CachedSessionUser> cachedAuthChecks = new ConcurrentHashMap<>();
  private final Map<String, CachedPremiumUser> premiumCache = new ConcurrentHashMap<>();
  private final Map<InetAddress, CachedBruteforceUser> bruteforceCache = new ConcurrentHashMap<>();
  private final Map<UUID, Runnable> postLoginTasks = new ConcurrentHashMap<>();
  private final Set<String> unsafePasswords = new HashSet<>();
  private final Set<String> forcedPreviously = Collections.synchronizedSet(new HashSet<>());

  private final HttpClient client = HttpClient.newHttpClient();

  private final ProxyServer server;
  private final Metrics.Factory metricsFactory;
  private final ExecutorService executor;
  private final Path dataDirectory;
  private final File dataDirectoryFile;
  private final File configFile;
  private final LimboFactory factory;
  private final FloodgateApiHolder floodgateApi;
  private final AsyncHttpClient httpClient;

  @Nullable
  private Component loginPremium;
  @Nullable
  private Title loginPremiumTitle;
  @Nullable
  private Component loginFloodgate;
  @Nullable
  private Title loginFloodgateTitle;
  private Component registrationsDisabledKick;
  private Component bruteforceAttemptKick;
  private Component nicknameInvalidKick;
  private Component reconnectKick;
  private ScheduledTask purgeCacheTask;
  private ScheduledTask purgePremiumCacheTask;
  private ScheduledTask purgeBruteforceCacheTask;

  private DSLContext dslContext;
  private Pattern nicknameValidationPattern;
  private Limbo authServer;

  @Inject
  public LimboAuth(Logger logger, ProxyServer server, Metrics.Factory metricsFactory, ExecutorService executor, @DataDirectory Path dataDirectory) {
    setLogger(logger);

    this.server = server;
    this.executor = executor;
    this.metricsFactory = metricsFactory;
    this.dataDirectory = dataDirectory;

    this.dataDirectoryFile = dataDirectory.toFile();
    this.configFile = new File(this.dataDirectoryFile, "config.yml");

    this.factory = (LimboFactory) this.server.getPluginManager().getPlugin("limboapi").flatMap(PluginContainer::getInstance).orElseThrow();

    if (this.server.getPluginManager().getPlugin("floodgate").isPresent()) {
      this.floodgateApi = new FloodgateApiHolder();
    } else {
      this.floodgateApi = null;
    }

    this.httpClient = ((VelocityServer) this.server).getAsyncHttpClient();
  }

  @Subscribe
  public void onProxyInitialization(ProxyInitializeEvent event) {
    System.setProperty("com.j256.simplelogging.level", "ERROR");

    try {
      this.reload();
    } catch (SQLRuntimeException exception) {
      LOGGER.error("SQL EXCEPTION CAUGHT.", exception);
      this.server.shutdown();
    }

    Metrics metrics = this.metricsFactory.make(this, 13700);
    metrics.addCustomChart(new SimplePie("floodgate_auth", () -> String.valueOf(Settings.IMP.MAIN.FLOODGATE_NEED_AUTH)));
    metrics.addCustomChart(new SimplePie("premium_auth", () -> String.valueOf(Settings.IMP.MAIN.ONLINE_MODE_NEED_AUTH)));
    metrics.addCustomChart(new SimplePie("db_type", () -> String.valueOf(Settings.IMP.DATABASE.STORAGE_TYPE)));
    metrics.addCustomChart(new SimplePie("load_world", () -> String.valueOf(Settings.IMP.MAIN.LOAD_WORLD)));
    metrics.addCustomChart(new SimplePie("totp_enabled", () -> String.valueOf(Settings.IMP.MAIN.ENABLE_TOTP)));
    metrics.addCustomChart(new SimplePie("dimension", () -> String.valueOf(Settings.IMP.MAIN.DIMENSION)));
    metrics.addCustomChart(new SimplePie("save_uuid", () -> String.valueOf(Settings.IMP.MAIN.SAVE_UUID)));
    metrics.addCustomChart(new SingleLineChart("registered_players", () -> Math.toIntExact(this.dslContext.fetchCount(RegisteredPlayer.Table.INSTANCE))));

    if (!UpdatesChecker.checkVersionByURL("https://raw.githubusercontent.com/Elytrium/LimboAuth/master/VERSION", Settings.IMP.VERSION)) {
      LOGGER.error("****************************************");
      LOGGER.warn("The new LimboAuth update was found, please update.");
      LOGGER.error("https://github.com/Elytrium/LimboAuth/releases/");
      LOGGER.error("****************************************");
    }
  }

  @SuppressFBWarnings(value = "NP_NULL_ON_SOME_PATH", justification = "LEGACY_AMPERSAND can't be null in velocity.")
  public void reload() {
    Settings.IMP.reload(this.configFile, Settings.IMP.PREFIX);

    if (this.floodgateApi == null && !Settings.IMP.MAIN.FLOODGATE_NEED_AUTH) {
      throw new IllegalStateException("If you want floodgate players to automatically pass auth (floodgate-need-auth: false),"
          + " please install floodgate plugin.");
    }

    ComponentSerializer<Component, Component, String> serializer = Settings.IMP.SERIALIZER.getSerializer();
    if (serializer == null) {
      LOGGER.warn("The specified serializer could not be founded, using default. (LEGACY_AMPERSAND)");
      setSerializer(new Serializer(Objects.requireNonNull(Serializers.LEGACY_AMPERSAND.getSerializer())));
    } else {
      setSerializer(new Serializer(serializer));
    }

    TaskEvent.reload();
    AuthSessionHandler.reload();

    this.loginPremium = Settings.IMP.MAIN.STRINGS.LOGIN_PREMIUM.isEmpty() ? null : SERIALIZER.deserialize(Settings.IMP.MAIN.STRINGS.LOGIN_PREMIUM);
    if (Settings.IMP.MAIN.STRINGS.LOGIN_PREMIUM_TITLE.isEmpty() && Settings.IMP.MAIN.STRINGS.LOGIN_PREMIUM_SUBTITLE.isEmpty()) {
      this.loginPremiumTitle = null;
    } else {
      this.loginPremiumTitle = Title.title(
          SERIALIZER.deserialize(Settings.IMP.MAIN.STRINGS.LOGIN_PREMIUM_TITLE),
          SERIALIZER.deserialize(Settings.IMP.MAIN.STRINGS.LOGIN_PREMIUM_SUBTITLE),
          Settings.IMP.MAIN.PREMIUM_TITLE_SETTINGS.toTimes()
      );
    }

    this.loginFloodgate = Settings.IMP.MAIN.STRINGS.LOGIN_FLOODGATE.isEmpty() ? null : SERIALIZER.deserialize(Settings.IMP.MAIN.STRINGS.LOGIN_FLOODGATE);
    if (Settings.IMP.MAIN.STRINGS.LOGIN_FLOODGATE_TITLE.isEmpty() && Settings.IMP.MAIN.STRINGS.LOGIN_FLOODGATE_SUBTITLE.isEmpty()) {
      this.loginFloodgateTitle = null;
    } else {
      this.loginFloodgateTitle = Title.title(
          SERIALIZER.deserialize(Settings.IMP.MAIN.STRINGS.LOGIN_FLOODGATE_TITLE),
          SERIALIZER.deserialize(Settings.IMP.MAIN.STRINGS.LOGIN_FLOODGATE_SUBTITLE),
          Settings.IMP.MAIN.PREMIUM_TITLE_SETTINGS.toTimes()
      );
    }

    this.bruteforceAttemptKick = SERIALIZER.deserialize(Settings.IMP.MAIN.STRINGS.LOGIN_WRONG_PASSWORD_KICK);
    this.nicknameInvalidKick = SERIALIZER.deserialize(Settings.IMP.MAIN.STRINGS.NICKNAME_INVALID_KICK);
    this.reconnectKick = SERIALIZER.deserialize(Settings.IMP.MAIN.STRINGS.RECONNECT_KICK);
    this.registrationsDisabledKick = SERIALIZER.deserialize(Settings.IMP.MAIN.STRINGS.REGISTRATIONS_DISABLED_KICK);

    if (Settings.IMP.MAIN.CHECK_PASSWORD_STRENGTH) {
      try {
        this.unsafePasswords.clear();
        Path unsafePasswordsPath = Paths.get(this.dataDirectoryFile.getAbsolutePath(), Settings.IMP.MAIN.UNSAFE_PASSWORDS_FILE);
        if (!unsafePasswordsPath.toFile().exists()) {
          Files.copy(Objects.requireNonNull(this.getClass().getResourceAsStream("/unsafe_passwords.txt")), unsafePasswordsPath);
        }

        try (Stream<String> unsafePasswordsStream = Files.lines(unsafePasswordsPath)) {
          this.unsafePasswords.addAll(unsafePasswordsStream.collect(Collectors.toList()));
        }
      } catch (IOException e) {
        throw new IllegalArgumentException(e);
      }
    }

    this.cachedAuthChecks.clear();
    this.premiumCache.clear();
    this.bruteforceCache.clear();

    Settings.DATABASE dbConfig = Settings.IMP.DATABASE;
    DatabaseLibrary databaseLibrary = dbConfig.STORAGE_TYPE;
    this.dslContext = databaseLibrary.connect(
        this.dataDirectoryFile.toPath().toAbsolutePath(),
        dbConfig.HOSTNAME,
        dbConfig.DATABASE,
        dbConfig.CONNECTION_PARAMETERS,
        dbConfig.USER,
        dbConfig.PASSWORD,
        this.executor
    );

    this.nicknameValidationPattern = Pattern.compile(Settings.IMP.MAIN.ALLOWED_NICKNAME_REGEX);
    this.migrateDb(RegisteredPlayer.Table.INSTANCE);

    CommandManager manager = this.server.getCommandManager();
    manager.unregister("unregister");
    manager.unregister("forceregister");
    manager.unregister("premium");
    manager.unregister("forceunregister");
    manager.unregister("changepassword");
    manager.unregister("forcechangepassword");
    manager.unregister("destroysession");
    manager.unregister("2fa");
    manager.unregister("limboauth");

    manager.register("unregister", new UnregisterCommand(this, this.dslContext), "unreg");
    manager.register("forceregister", new ForceRegisterCommand(this, this.dslContext), "forcereg");
    manager.register("premium", new PremiumCommand(this, this.dslContext), "license");
    manager.register("forceunregister", new ForceUnregisterCommand(this, this.server, this.dslContext), "forceunreg");
    manager.register("changepassword", new ChangePasswordCommand(this, this.dslContext), "changepass", "cp");
    manager.register("forcechangepassword", new ForceChangePasswordCommand(this, this.server, this.dslContext), "forcechangepass", "fcp");
    manager.register("destroysession", new DestroySessionCommand(this), "logout");
    if (Settings.IMP.MAIN.ENABLE_TOTP) {
      manager.register("2fa", new TotpCommand(this.dslContext), "totp");
    }
    manager.register("limboauth", new LimboAuthCommand(this), "la", "auth", "lauth");

    Settings.Main.AUTH_COORDS authCoords = Settings.IMP.MAIN.AUTH_COORDS;
    VirtualWorld authWorld = this.factory.createVirtualWorld(
        Settings.IMP.MAIN.DIMENSION,
        authCoords.X, authCoords.Y, authCoords.Z,
        (float) authCoords.YAW, (float) authCoords.PITCH
    );

    if (Settings.IMP.MAIN.LOAD_WORLD) {
      try {
        Path path = this.dataDirectory.resolve(Settings.IMP.MAIN.WORLD_FILE_PATH);
        WorldFile file = this.factory.openWorldFile(Settings.IMP.MAIN.WORLD_FILE_TYPE, path);

        Settings.Main.WORLD_COORDS coords = Settings.IMP.MAIN.WORLD_COORDS;
        file.toWorld(this.factory, authWorld, coords.X, coords.Y, coords.Z, Settings.IMP.MAIN.WORLD_LIGHT_LEVEL);
      } catch (IOException e) {
        throw new IllegalArgumentException(e);
      }
    }

    if (this.authServer != null) {
      this.authServer.dispose();
    }

    this.authServer = this.factory
        .createLimbo(authWorld)
        .setName("LimboAuth")
        .setWorldTime(Settings.IMP.MAIN.WORLD_TICKS)
        .setGameMode(Settings.IMP.MAIN.GAME_MODE)
        .registerCommand(new LimboCommandMeta(this.filterCommands(Settings.IMP.MAIN.REGISTER_COMMAND)))
        .registerCommand(new LimboCommandMeta(this.filterCommands(Settings.IMP.MAIN.LOGIN_COMMAND)));

    if (Settings.IMP.MAIN.ENABLE_TOTP) {
      this.authServer.registerCommand(new LimboCommandMeta(this.filterCommands(Settings.IMP.MAIN.TOTP_COMMAND)));
    }

    EventManager eventManager = this.server.getEventManager();
    eventManager.unregisterListeners(this);
    eventManager.register(this, new AuthListener(this, this.dslContext, this.floodgateApi));

    if (this.purgeCacheTask != null) {
      this.purgeCacheTask.cancel();
    }

    this.purgeCacheTask = this.server.getScheduler()
        .buildTask(this, () -> this.checkCache(this.cachedAuthChecks, Settings.IMP.MAIN.PURGE_CACHE_MILLIS))
        .delay(Settings.IMP.MAIN.PURGE_CACHE_MILLIS, TimeUnit.MILLISECONDS)
        .repeat(Settings.IMP.MAIN.PURGE_CACHE_MILLIS, TimeUnit.MILLISECONDS)
        .schedule();

    if (this.purgePremiumCacheTask != null) {
      this.purgePremiumCacheTask.cancel();
    }

    this.purgePremiumCacheTask = this.server.getScheduler()
        .buildTask(this, () -> this.checkCache(this.premiumCache, Settings.IMP.MAIN.PURGE_PREMIUM_CACHE_MILLIS))
        .delay(Settings.IMP.MAIN.PURGE_PREMIUM_CACHE_MILLIS, TimeUnit.MILLISECONDS)
        .repeat(Settings.IMP.MAIN.PURGE_PREMIUM_CACHE_MILLIS, TimeUnit.MILLISECONDS)
        .schedule();

    if (this.purgeBruteforceCacheTask != null) {
      this.purgeBruteforceCacheTask.cancel();
    }

    this.purgeBruteforceCacheTask = this.server.getScheduler()
        .buildTask(this, () -> this.checkCache(this.bruteforceCache, Settings.IMP.MAIN.PURGE_BRUTEFORCE_CACHE_MILLIS))
        .delay(Settings.IMP.MAIN.PURGE_BRUTEFORCE_CACHE_MILLIS, TimeUnit.MILLISECONDS)
        .repeat(Settings.IMP.MAIN.PURGE_BRUTEFORCE_CACHE_MILLIS, TimeUnit.MILLISECONDS)
        .schedule();

    eventManager.fireAndForget(new AuthPluginReloadEvent());
  }

  private List<String> filterCommands(List<String> commands) {
    return commands.stream().filter(command -> command.startsWith("/")).map(command -> command.substring(1)).collect(Collectors.toList());
  }

  private void checkCache(Map<?, ? extends CachedUser> userMap, long time) {
    userMap.entrySet().stream()
        .filter(userEntry -> userEntry.getValue().getCheckTime() + time <= System.currentTimeMillis())
        .map(Map.Entry::getKey)
        .forEach(userMap::remove);
  }

  public void migrateDb(Table<?> table) {
    Field<?>[] fields = table.fields();
    this.dslContext.createTableIfNotExists(table).columns(fields).execute();
    for (Field<?> field : fields) {
      this.dslContext.alterTable(table).addColumnIfNotExists(field).execute();
    }
  }

  public void cacheAuthUser(Player player) {
    String username = player.getUsername();
    String lowercaseUsername = username.toLowerCase(Locale.ROOT);
    this.cachedAuthChecks.put(lowercaseUsername, new CachedSessionUser(System.currentTimeMillis(), player.getRemoteAddress().getAddress(), username));
  }

  public void removePlayerFromCache(String username) {
    this.cachedAuthChecks.remove(username.toLowerCase(Locale.ROOT));
    this.premiumCache.remove(username.toLowerCase(Locale.ROOT));
  }

  public boolean needAuth(Player player) {
    String username = player.getUsername();
    String lowercaseUsername = username.toLowerCase(Locale.ROOT);
    if (!this.cachedAuthChecks.containsKey(lowercaseUsername)) {
      return true;
    } else {
      CachedSessionUser sessionUser = this.cachedAuthChecks.get(lowercaseUsername);
      return !sessionUser.getInetAddress().equals(player.getRemoteAddress().getAddress()) || !sessionUser.getUsername().equals(username);
    }
  }

  public void authPlayer(Player player) {
    boolean isFloodgate = !Settings.IMP.MAIN.FLOODGATE_NEED_AUTH && this.floodgateApi.isFloodgatePlayer(player.getUniqueId());
    if (!isFloodgate && this.isForcedPreviously(player.getUsername())) {
      this.isPremium(player.getUsername()).thenAccept(isPremium -> {
        if (isPremium) {
          player.disconnect(this.reconnectKick);
        } else {
          this.authPlayer0(player, false);
        }
      });
    } else {
      this.authPlayer0(player, isFloodgate);
    }
  }

  private void authPlayer0(Player player, boolean isFloodgate) {
    if (this.getBruteforceAttempts(player.getRemoteAddress().getAddress()) >= Settings.IMP.MAIN.BRUTEFORCE_MAX_ATTEMPTS) {
      player.disconnect(this.bruteforceAttemptKick);
      return;
    }

    String nickname = player.getUsername();
    String lowercaseNickname = nickname.toLowerCase(Locale.ROOT);
    if (!this.nicknameValidationPattern.matcher((isFloodgate) ? nickname.substring(this.floodgateApi.getPrefixLength()) : nickname).matches()) {
      player.disconnect(this.nicknameInvalidKick);
      return;
    }

    this.dslContext.fetchAsync(RegisteredPlayer.Table.INSTANCE,
            DSL.field(RegisteredPlayer.Table.LOWERCASE_NICKNAME_FIELD).eq(lowercaseNickname))
        .thenAccept(registeredPlayerResult -> {
          boolean onlineMode = player.isOnlineMode();
          RegisteredPlayer nicknameRegisteredPlayer = registeredPlayerResult.isEmpty() ? null : registeredPlayerResult.get(0);
          if ((onlineMode || isFloodgate) && (nicknameRegisteredPlayer == null || nicknameRegisteredPlayer.getHash().isEmpty())) {
            this.dslContext.fetchAsync(RegisteredPlayer.Table.INSTANCE,
                    DSL.field(RegisteredPlayer.Table.LOWERCASE_NICKNAME_FIELD).eq(lowercaseNickname))
                .thenAccept(premiumRegisteredPlayerResult -> {
                  RegisteredPlayer registeredPlayer = premiumRegisteredPlayerResult.isEmpty() ? null : registeredPlayerResult.get(0);
                  if (nicknameRegisteredPlayer != null && registeredPlayer == null && nicknameRegisteredPlayer.getHash().isEmpty()) {
                    registeredPlayer = nicknameRegisteredPlayer;
                    registeredPlayer.setPremiumUuid(player.getUniqueId().toString());
                    this.dslContext.update(RegisteredPlayer.Table.INSTANCE)
                        .set(RegisteredPlayer.Table.PREMIUM_UUID_FIELD, player.getUniqueId().toString())
                        .executeAsync();
                  }

                  if (nicknameRegisteredPlayer == null && registeredPlayer == null && Settings.IMP.MAIN.SAVE_PREMIUM_ACCOUNTS) {
                    registeredPlayer = new RegisteredPlayer(player).setPremiumUuid(player.getUniqueId());

                    this.dslContext.insertInto(RegisteredPlayer.Table.INSTANCE)
                        .values(registeredPlayer)
                        .executeAsync();
                  }

                  TaskEvent.Result result = TaskEvent.Result.NORMAL;
                  if (registeredPlayer == null || registeredPlayer.getHash().isEmpty()) {
                    // Due to the current connection state, which is set to LOGIN there, we cannot send the packets.
                    // We need to wait for the PLAY connection state to set.
                    this.postLoginTasks.put(player.getUniqueId(), () -> {
                      if (onlineMode) {
                        if (this.loginPremium != null) {
                          player.sendMessage(this.loginPremium);
                        }
                        if (this.loginPremiumTitle != null) {
                          player.showTitle(this.loginPremiumTitle);
                        }
                      } else {
                        if (this.loginFloodgate != null) {
                          player.sendMessage(this.loginFloodgate);
                        }
                        if (this.loginFloodgateTitle != null) {
                          player.showTitle(this.loginFloodgateTitle);
                        }
                      }
                    });

                    result = TaskEvent.Result.BYPASS;
                  }

                  this.authPlayer0(player, registeredPlayer, result);
                });
          } else {
            this.authPlayer0(player, nicknameRegisteredPlayer, TaskEvent.Result.NORMAL);
          }
        });
  }

  private void authPlayer0(Player player, RegisteredPlayer registeredPlayer, TaskEvent.Result result) {
    EventManager eventManager = this.server.getEventManager();
    if (registeredPlayer == null) {
      if (Settings.IMP.MAIN.DISABLE_REGISTRATIONS) {
        player.disconnect(this.registrationsDisabledKick);
        return;
      }

      Consumer<TaskEvent> eventConsumer = (event) -> this.sendPlayer(event, null);
      eventManager.fire(new PreRegisterEvent(eventConsumer, result, player)).thenAcceptAsync(eventConsumer);
    } else {
      Consumer<TaskEvent> eventConsumer = (event) -> this.sendPlayer(event, ((PreAuthorizationEvent) event).getPlayerInfo());
      eventManager.fire(new PreAuthorizationEvent(eventConsumer, result, player, registeredPlayer)).thenAcceptAsync(eventConsumer);
    }
  }

  private void sendPlayer(TaskEvent event, RegisteredPlayer registeredPlayer) {
    Player player = ((PreEvent) event).getPlayer();

    switch (event.getResult()) {
      case BYPASS: {
        this.factory.passLoginLimbo(player);
        this.cacheAuthUser(player);
        try {
          this.updateLoginData(player);
        } catch (SQLException e) {
          throw new SQLRuntimeException(e);
        }
        break;
      }
      case CANCEL: {
        player.disconnect(event.getReason());
        break;
      }
      case WAIT: {
        return;
      }
      case NORMAL:
      default: {
        this.authServer.spawnPlayer(player, new AuthSessionHandler(this.dslContext, player, this, registeredPlayer));
        break;
      }
    }
  }

  public void updateLoginData(Player player) throws SQLException {
    String lowercaseNickname = player.getUsername().toLowerCase(Locale.ROOT);
    this.dslContext.update(RegisteredPlayer.Table.INSTANCE)
        .set(RegisteredPlayer.Table.LOGIN_IP_FIELD, player.getRemoteAddress().getAddress().getHostAddress())
        .set(RegisteredPlayer.Table.LOGIN_DATE_FIELD, System.currentTimeMillis())
        .where(DSL.field(RegisteredPlayer.LOWERCASE_NICKNAME_FIELD).eq(lowercaseNickname))
        .executeAsync()
        .thenRun(() -> {
          if (Settings.IMP.MAIN.MOD.ENABLED) {
            byte[] lowercaseNicknameSerialized = lowercaseNickname.getBytes(StandardCharsets.UTF_8);
            long issueTime = System.currentTimeMillis();
            long hash = SipHasher.init(Settings.IMP.MAIN.MOD.VERIFY_KEY)
                .update(lowercaseNicknameSerialized)
                .update(Longs.toByteArray(issueTime))
                .digest();

            player.sendPluginMessage(this.getChannelIdentifier(player), Bytes.concat(Longs.toByteArray(issueTime), Longs.toByteArray(hash)));
          }
        });
  }

  public ChannelIdentifier getChannelIdentifier(Player player) {
    return player.getProtocolVersion().compareTo(ProtocolVersion.MINECRAFT_1_13) >= 0 ? MOD_CHANNEL : LEGACY_MOD_CHANNEL;
  }

  private boolean validateScheme(JsonElement jsonElement, List<String> scheme) {
    if (!scheme.isEmpty()) {
      if (!(jsonElement instanceof JsonObject)) {
        return false;
      }

      JsonObject object = (JsonObject) jsonElement;
      for (String field : scheme) {
        if (!object.has(field)) {
          return false;
        }
      }
    }

    return true;
  }

  public CompletableFuture<PremiumResponse> isPremiumExternal(String nickname) {
    CompletableFuture<PremiumResponse> completableFuture = new CompletableFuture<>();
    ListenableFuture<Response> responseListenable = this.httpClient.prepareGet(String.format(Settings.IMP.MAIN.ISPREMIUM_AUTH_URL,
            UrlEscapers.urlFormParameterEscaper().escape(nickname)))
        .execute();

    responseListenable.addListener(() -> {
      try {
        Response response = responseListenable.get();
        int statusCode = response.getStatusCode();

        if (Settings.IMP.MAIN.STATUS_CODE_RATE_LIMIT.contains(statusCode)) {
          completableFuture.complete(PremiumResponse.RATE_LIMIT);
          return;
        }

        JsonElement jsonElement = JsonParser.parseString(response.getResponseBody());

        if (Settings.IMP.MAIN.STATUS_CODE_USER_EXISTS.contains(statusCode)
            && this.validateScheme(jsonElement, Settings.IMP.MAIN.USER_EXISTS_JSON_VALIDATOR_FIELDS)) {
          completableFuture.complete(new PremiumResponse(PremiumState.PREMIUM_USERNAME, ((JsonObject) jsonElement).get(Settings.IMP.MAIN.JSON_UUID_FIELD).getAsString()));
          return;
        }

        if (Settings.IMP.MAIN.STATUS_CODE_USER_NOT_EXISTS.contains(statusCode)
            && this.validateScheme(jsonElement, Settings.IMP.MAIN.USER_NOT_EXISTS_JSON_VALIDATOR_FIELDS)) {
          completableFuture.complete(PremiumResponse.CRACKED);
          return;
        }

        completableFuture.complete(PremiumResponse.ERROR);
      } catch (ExecutionException | InterruptedException e) {
        LOGGER.error("Unable to authenticate with Mojang.", e);
        completableFuture.complete(PremiumResponse.ERROR);
      }
    }, this.executor);

    return completableFuture;
  }

  public CompletableFuture<PremiumResponse> isPremiumInternal(String nickname) {
    CompletableFuture<PremiumResponse> completableFuture = new CompletableFuture<>();
    this.dslContext.selectCount().from(RegisteredPlayer.Table.INSTANCE)
        .where(DSL.field(RegisteredPlayer.LOWERCASE_NICKNAME_FIELD).eq(nickname)
            .and(DSL.field(RegisteredPlayer.HASH_FIELD).ne("")))
        .fetchAsync()
        .thenAccept(crackedCountResult -> {
          if (crackedCountResult.get(0).getValue(0, Integer.class) != 0) {
            completableFuture.complete(PremiumResponse.CRACKED);
          } else {
            completableFuture.complete(PremiumResponse.UNKNOWN);
          }
        })
        .exceptionally(e -> {
          LOGGER.error("Unable to check if account is premium.", e);
          completableFuture.complete(PremiumResponse.ERROR);
          return null;
        });

    this.dslContext.selectCount().from(RegisteredPlayer.Table.INSTANCE)
        .where(DSL.field(RegisteredPlayer.LOWERCASE_NICKNAME_FIELD).eq(nickname)
            .and(DSL.field(RegisteredPlayer.HASH_FIELD).eq("")))
        .fetchAsync()
        .thenAccept(premiumCountResult -> {
          if (premiumCountResult.get(0).getValue(0, Integer.class) != 0) {
            completableFuture.complete(PremiumResponse.PREMIUM);
          } else {
            completableFuture.complete(PremiumResponse.UNKNOWN);
          }
        })
        .exceptionally(e -> {
          LOGGER.error("Unable to check if account is premium.", e);
          completableFuture.complete(PremiumResponse.ERROR);
          return null;
        });

    return completableFuture;
  }

  public CompletableFuture<Boolean> isPremiumUuid(UUID uuid) {
    CompletableFuture<Boolean> completableFuture = new CompletableFuture<>();
    this.dslContext.selectCount().from(RegisteredPlayer.Table.INSTANCE)
        .where(DSL.field(RegisteredPlayer.PREMIUM_UUID_FIELD).eq(uuid)
            .and(DSL.field(RegisteredPlayer.HASH_FIELD).eq("")))
        .fetchAsync()
        .thenAccept(result -> completableFuture.complete(result.get(0).getValue(0, Integer.class) != 0));
    return completableFuture;
  }

  private CompletableFuture<Boolean> checkIsPremiumAndCacheFinal(String nickname, String lowercaseNickname, boolean premium,
                                                                 boolean unknown, boolean wasRateLimited, boolean wasError, UUID uuid) {
    if (unknown) {
      if (uuid != null) {
        CompletableFuture<Boolean> isPremiumFuture = new CompletableFuture<>();

        this.isPremiumUuid(uuid).thenAccept(isPremium -> {
          if (isPremium) {
            this.premiumCache.put(lowercaseNickname, new CachedPremiumUser(System.currentTimeMillis(), true));
            isPremiumFuture.complete(true);
          }
        });

        return isPremiumFuture;
      }

      if (Settings.IMP.MAIN.ONLINE_MODE_NEED_AUTH) {
        return CompletableFuture.completedFuture(false);
      }
    }

    if (wasRateLimited && unknown || wasRateLimited && wasError) {
      return CompletableFuture.completedFuture(Settings.IMP.MAIN.ON_RATE_LIMIT_PREMIUM);
    }

    if (wasError && unknown || !premium) {
      return CompletableFuture.completedFuture(Settings.IMP.MAIN.ON_SERVER_ERROR_PREMIUM);
    }

    this.premiumCache.put(lowercaseNickname, new CachedPremiumUser(System.currentTimeMillis(), true));
    return CompletableFuture.completedFuture(true);
  }

  private CompletableFuture<Boolean> checkIsPremiumAndCacheStep(Queue<Function<String, CompletableFuture<PremiumResponse>>> queue,
                                                                String nickname, String lowercaseNickname, boolean premiumFinal, boolean unknownFinal,
                                                                boolean wasRateLimitedFinal, boolean wasErrorFinal, UUID uuidFinal) {
    if (queue.isEmpty()) {
      return this.checkIsPremiumAndCacheFinal(nickname, lowercaseNickname, premiumFinal, unknownFinal, wasRateLimitedFinal, wasErrorFinal, uuidFinal);
    }

    return queue.poll().apply(lowercaseNickname).thenCompose(check -> {
      boolean premium = premiumFinal;
      boolean unknown = unknownFinal;
      boolean wasRateLimited = wasRateLimitedFinal;
      boolean wasError = wasErrorFinal;
      UUID uuid;

      if (check.getUuid() != null) {
        uuid = check.getUuid();
      } else {
        uuid = uuidFinal;
      }

      switch (check.getState()) {
        case CRACKED: {
          this.premiumCache.put(lowercaseNickname, new CachedPremiumUser(System.currentTimeMillis(), false));
          return CompletableFuture.completedFuture(false);
        }
        case PREMIUM: {
          this.premiumCache.put(lowercaseNickname, new CachedPremiumUser(System.currentTimeMillis(), true));
          return CompletableFuture.completedFuture(true);
        }
        case PREMIUM_USERNAME: {
          premium = true;
          break;
        }
        case UNKNOWN: {
          unknown = true;
          break;
        }
        case RATE_LIMIT: {
          wasRateLimited = true;
          break;
        }
        default:
        case ERROR: {
          wasError = true;
          break;
        }
      }

      return this.checkIsPremiumAndCacheStep(queue, nickname, lowercaseNickname, premium, unknown, wasRateLimited, wasError, uuid);
    });
  }

  private CompletableFuture<Boolean> checkIsPremiumAndCache(String nickname, Queue<Function<String, CompletableFuture<PremiumResponse>>> queue) {
    String lowercaseNickname = nickname.toLowerCase(Locale.ROOT);
    if (this.premiumCache.containsKey(lowercaseNickname)) {
      return CompletableFuture.completedFuture(this.premiumCache.get(lowercaseNickname).isPremium());
    }

    boolean premium = false;
    boolean unknown = false;
    boolean wasRateLimited = false;
    boolean wasError = false;
    UUID uuid = null;

    return this.checkIsPremiumAndCacheStep(queue, nickname, lowercaseNickname, premium, unknown, wasRateLimited, wasError, uuid);
  }

  public CompletableFuture<Boolean> isPremium(String nickname) {
    if (Settings.IMP.MAIN.FORCE_OFFLINE_MODE) {
      return CompletableFuture.completedFuture(false);
    } else {
      if (Settings.IMP.MAIN.CHECK_PREMIUM_PRIORITY_INTERNAL) {
        return this.checkIsPremiumAndCache(nickname, new ArrayDeque<>(List.of(this::isPremiumInternal, this::isPremiumExternal)));
      } else {
        return this.checkIsPremiumAndCache(nickname, new ArrayDeque<>(List.of(this::isPremiumExternal, this::isPremiumInternal)));
      }
    }
  }

  public void incrementBruteforceAttempts(InetAddress address) {
    this.getBruteforceUser(address).incrementAttempts();
  }

  public int getBruteforceAttempts(InetAddress address) {
    return this.getBruteforceUser(address).getAttempts();
  }

  private CachedBruteforceUser getBruteforceUser(InetAddress address) {
    CachedBruteforceUser user = this.bruteforceCache.get(address);
    if (user == null) {
      user = new CachedBruteforceUser(System.currentTimeMillis());
      this.bruteforceCache.put(address, user);
    }

    return user;
  }

  public void clearBruteforceAttempts(InetAddress address) {
    this.bruteforceCache.remove(address);
  }

  public void saveForceOfflineMode(String nickname) {
    this.forcedPreviously.add(nickname);
  }

  public void unsetForcedPreviously(String nickname) {
    this.forcedPreviously.remove(nickname);
  }

  public boolean isForcedPreviously(String nickname) {
    return this.forcedPreviously.contains(nickname);
  }

  public Map<UUID, Runnable> getPostLoginTasks() {
    return this.postLoginTasks;
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

  private static void setLogger(Logger logger) {
    LOGGER = logger;
  }

  private static void setSerializer(Serializer serializer) {
    SERIALIZER = serializer;
  }

  public static Serializer getSerializer() {
    return SERIALIZER;
  }

  public Limbo getAuthServer() {
    return this.authServer;
  }

  public Pattern getNicknameValidationPattern() {
    return this.nicknameValidationPattern;
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
