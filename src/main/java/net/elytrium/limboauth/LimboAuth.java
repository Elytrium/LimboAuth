/*
 * Copyright (C) 2021 - 2022 Elytrium
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

import com.google.gson.JsonElement;
import com.google.gson.JsonObject;
import com.google.gson.JsonParser;
import com.google.inject.Inject;
import com.j256.ormlite.dao.Dao;
import com.j256.ormlite.dao.DaoManager;
import com.j256.ormlite.dao.GenericRawResults;
import com.j256.ormlite.db.DatabaseType;
import com.j256.ormlite.field.FieldType;
import com.j256.ormlite.jdbc.JdbcPooledConnectionSource;
import com.j256.ormlite.stmt.QueryBuilder;
import com.j256.ormlite.table.TableInfo;
import com.j256.ormlite.table.TableUtils;
import com.velocitypowered.api.command.CommandManager;
import com.velocitypowered.api.event.EventManager;
import com.velocitypowered.api.event.Subscribe;
import com.velocitypowered.api.event.proxy.ProxyInitializeEvent;
import com.velocitypowered.api.plugin.Dependency;
import com.velocitypowered.api.plugin.Plugin;
import com.velocitypowered.api.plugin.PluginContainer;
import com.velocitypowered.api.plugin.annotation.DataDirectory;
import com.velocitypowered.api.proxy.Player;
import com.velocitypowered.api.proxy.ProxyServer;
import com.velocitypowered.api.scheduler.ScheduledTask;
import edu.umd.cs.findbugs.annotations.SuppressFBWarnings;
import java.io.File;
import java.io.IOException;
import java.net.InetAddress;
import java.net.URI;
import java.net.URLEncoder;
import java.net.http.HttpClient;
import java.net.http.HttpRequest;
import java.net.http.HttpResponse;
import java.nio.charset.StandardCharsets;
import java.nio.file.Files;
import java.nio.file.Path;
import java.nio.file.Paths;
import java.sql.SQLException;
import java.util.Collections;
import java.util.HashSet;
import java.util.List;
import java.util.Locale;
import java.util.Map;
import java.util.Objects;
import java.util.Set;
import java.util.UUID;
import java.util.concurrent.ConcurrentHashMap;
import java.util.concurrent.TimeUnit;
import java.util.function.Consumer;
import java.util.function.Function;
import java.util.regex.Pattern;
import java.util.stream.Collectors;
import java.util.stream.Stream;
import net.elytrium.java.commons.mc.serialization.Serializer;
import net.elytrium.java.commons.mc.serialization.Serializers;
import net.elytrium.java.commons.updates.UpdatesChecker;
import net.elytrium.limboapi.api.Limbo;
import net.elytrium.limboapi.api.LimboFactory;
import net.elytrium.limboapi.api.chunk.Dimension;
import net.elytrium.limboapi.api.chunk.VirtualWorld;
import net.elytrium.limboapi.api.command.LimboCommandMeta;
import net.elytrium.limboapi.api.file.SchematicFile;
import net.elytrium.limboapi.api.file.StructureFile;
import net.elytrium.limboapi.api.file.WorldFile;
import net.elytrium.limboapi.api.player.GameMode;
import net.elytrium.limboauth.command.ChangePasswordCommand;
import net.elytrium.limboauth.command.DestroySessionCommand;
import net.elytrium.limboauth.command.ForceChangePasswordCommand;
import net.elytrium.limboauth.command.ForceUnregisterCommand;
import net.elytrium.limboauth.command.LimboAuthCommand;
import net.elytrium.limboauth.command.PremiumCommand;
import net.elytrium.limboauth.command.TotpCommand;
import net.elytrium.limboauth.command.UnregisterCommand;
import net.elytrium.limboauth.event.AuthPluginReloadEvent;
import net.elytrium.limboauth.event.PreAuthorizationEvent;
import net.elytrium.limboauth.event.PreEvent;
import net.elytrium.limboauth.event.PreRegisterEvent;
import net.elytrium.limboauth.event.TaskEvent;
import net.elytrium.limboauth.floodgate.FloodgateApiHolder;
import net.elytrium.limboauth.handler.AuthSessionHandler;
import net.elytrium.limboauth.listener.AuthListener;
import net.elytrium.limboauth.model.RegisteredPlayer;
import net.kyori.adventure.text.Component;
import net.kyori.adventure.text.serializer.ComponentSerializer;
import net.kyori.adventure.title.Title;
import org.bstats.charts.SimplePie;
import org.bstats.charts.SingleLineChart;
import org.bstats.velocity.Metrics;
import org.checkerframework.checker.nullness.qual.MonotonicNonNull;
import org.checkerframework.checker.nullness.qual.Nullable;
import org.slf4j.Logger;

@Plugin(
    id = "limboauth",
    name = "LimboAuth",
    version = BuildConstants.AUTH_VERSION,
    url = "https://elytrium.net/",
    authors = {
        "hevav",
        "mdxd44"
    },
    dependencies = {
        @Dependency(id = "limboapi"),
        @Dependency(id = "floodgate", optional = true)
    }
)
public class LimboAuth {

  @MonotonicNonNull
  private static Logger LOGGER;
  @MonotonicNonNull
  private static Serializer SERIALIZER;

  private final Map<String, CachedSessionUser> cachedAuthChecks = new ConcurrentHashMap<>();
  private final Map<String, CachedPremiumUser> premiumCache = new ConcurrentHashMap<>();
  private final Map<UUID, Runnable> postLoginTasks = new ConcurrentHashMap<>();
  private final Set<String> unsafePasswords = new HashSet<>();

  private final HttpClient client = HttpClient.newHttpClient();

  private final ProxyServer server;
  private final Metrics.Factory metricsFactory;
  private final Path dataDirectory;
  private final File dataDirectoryFile;
  private final File configFile;
  private final LimboFactory factory;
  private final FloodgateApiHolder floodgateApi;

  @Nullable
  private Component loginPremium;
  @Nullable
  private Title loginPremiumTitle;
  @Nullable
  private Component loginFloodgate;
  @Nullable
  private Title loginFloodgateTitle;
  private Component registrationsDisabledKick;
  private Component nicknameInvalidKick;
  private ScheduledTask purgeCacheTask;
  private ScheduledTask purgePremiumCacheTask;

  private JdbcPooledConnectionSource connectionSource;
  private Dao<RegisteredPlayer, String> playerDao;
  private Pattern nicknameValidationPattern;
  private Limbo authServer;

  @Inject
  public LimboAuth(Logger logger, ProxyServer server, Metrics.Factory metricsFactory, @DataDirectory Path dataDirectory) {
    setLogger(logger);

    this.server = server;
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
  }

  @Subscribe
  public void onProxyInitialization(ProxyInitializeEvent event) throws Exception {
    System.setProperty("com.j256.simplelogging.level", "ERROR");

    this.reload();

    Metrics metrics = this.metricsFactory.make(this, 13700);
    metrics.addCustomChart(new SimplePie("floodgate_auth", () -> String.valueOf(Settings.IMP.MAIN.FLOODGATE_NEED_AUTH)));
    metrics.addCustomChart(new SimplePie("premium_auth", () -> String.valueOf(Settings.IMP.MAIN.ONLINE_MODE_NEED_AUTH)));
    metrics.addCustomChart(new SimplePie("db_type", () -> Settings.IMP.DATABASE.STORAGE_TYPE));
    metrics.addCustomChart(new SimplePie("load_world", () -> String.valueOf(Settings.IMP.MAIN.LOAD_WORLD)));
    metrics.addCustomChart(new SimplePie("totp_enabled", () -> String.valueOf(Settings.IMP.MAIN.ENABLE_TOTP)));
    metrics.addCustomChart(new SimplePie("dimension", () -> Settings.IMP.MAIN.DIMENSION));
    metrics.addCustomChart(new SimplePie("save_uuid", () -> String.valueOf(Settings.IMP.MAIN.SAVE_UUID)));
    metrics.addCustomChart(new SingleLineChart("registered_players", () -> Math.toIntExact(this.playerDao.countOf())));

    if (!UpdatesChecker.checkVersionByURL("https://raw.githubusercontent.com/Elytrium/LimboAuth/master/VERSION", Settings.IMP.VERSION)) {
      LOGGER.error("****************************************");
      LOGGER.warn("The new LimboAuth update was found, please update.");
      LOGGER.error("https://github.com/Elytrium/LimboAuth/releases/");
      LOGGER.error("****************************************");
    }
  }

  @SuppressFBWarnings(value = "NP_NULL_ON_SOME_PATH", justification = "LEGACY_AMPERSAND can't be null in velocity.")
  public void reload() throws Exception {
    Settings.IMP.reload(this.configFile, Settings.IMP.PREFIX);

    if (this.floodgateApi == null && !Settings.IMP.MAIN.FLOODGATE_NEED_AUTH) {
      throw new IllegalStateException("If you want floodgate players to automatically pass auth (floodgate-need-auth: false),"
          + " please install floodgate plugin.");
    }

    ComponentSerializer<Component, Component, String> serializer = Serializers.valueOf(Settings.IMP.SERIALIZER.toUpperCase(Locale.ROOT)).getSerializer();
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

    this.nicknameInvalidKick = SERIALIZER.deserialize(Settings.IMP.MAIN.STRINGS.NICKNAME_INVALID_KICK);
    this.registrationsDisabledKick = SERIALIZER.deserialize(Settings.IMP.MAIN.STRINGS.REGISTRATIONS_DISABLED_KICK);

    if (Settings.IMP.MAIN.CHECK_PASSWORD_STRENGTH) {
      this.unsafePasswords.clear();
      Path unsafePasswordsPath = Paths.get(this.dataDirectoryFile.getAbsolutePath(), Settings.IMP.MAIN.UNSAFE_PASSWORDS_FILE);
      if (!unsafePasswordsPath.toFile().exists()) {
        Files.copy(Objects.requireNonNull(this.getClass().getResourceAsStream("/unsafe_passwords.txt")), unsafePasswordsPath);
      }

      try (Stream<String> unsafePasswordsStream = Files.lines(unsafePasswordsPath)) {
        this.unsafePasswords.addAll(unsafePasswordsStream.collect(Collectors.toList()));
      }
    }

    this.cachedAuthChecks.clear();

    Settings.DATABASE dbConfig = Settings.IMP.DATABASE;
    switch (dbConfig.STORAGE_TYPE.toLowerCase(Locale.ROOT)) {
      case "h2": {
        this.connectionSource = new JdbcPooledConnectionSource("jdbc:h2:" + this.dataDirectoryFile.getAbsoluteFile() + "/limboauth");
        break;
      }
      case "mysql": {
        this.connectionSource = new JdbcPooledConnectionSource(
            "jdbc:mysql://" + dbConfig.HOSTNAME + "/" + dbConfig.DATABASE + dbConfig.CONNECTION_PARAMETERS, dbConfig.USER, dbConfig.PASSWORD
        );
        break;
      }
      case "postgresql": {
        this.connectionSource = new JdbcPooledConnectionSource(
            "jdbc:postgresql://" + dbConfig.HOSTNAME + "/" + dbConfig.DATABASE + dbConfig.CONNECTION_PARAMETERS, dbConfig.USER, dbConfig.PASSWORD
        );
        break;
      }
      default: {
        LOGGER.error("Wrong database type.");
        this.server.shutdown();
        return;
      }
    }

    this.nicknameValidationPattern = Pattern.compile(Settings.IMP.MAIN.ALLOWED_NICKNAME_REGEX);

    try {
      TableUtils.createTableIfNotExists(this.connectionSource, RegisteredPlayer.class);
      this.playerDao = DaoManager.createDao(this.connectionSource, RegisteredPlayer.class);
      this.migrateDb(this.playerDao);
    } catch (SQLException e) {
      e.printStackTrace();
    }

    CommandManager manager = this.server.getCommandManager();
    manager.unregister("unregister");
    manager.unregister("premium");
    manager.unregister("forceunregister");
    manager.unregister("changepassword");
    manager.unregister("forcechangepassword");
    manager.unregister("destroysession");
    manager.unregister("2fa");
    manager.unregister("limboauth");

    manager.register("unregister", new UnregisterCommand(this, this.playerDao), "unreg");
    manager.register("premium", new PremiumCommand(this, this.playerDao), "license");
    manager.register("forceunregister", new ForceUnregisterCommand(this, this.server, this.playerDao), "forceunreg");
    manager.register("changepassword", new ChangePasswordCommand(this.playerDao), "changepass");
    manager.register("forcechangepassword", new ForceChangePasswordCommand(this.server, this.playerDao), "forcechangepass");
    manager.register("destroysession", new DestroySessionCommand(this));
    if (Settings.IMP.MAIN.ENABLE_TOTP) {
      manager.register("2fa", new TotpCommand(this.playerDao), "totp");
    }
    manager.register("limboauth", new LimboAuthCommand(this), "la", "auth", "lauth");

    Settings.MAIN.AUTH_COORDS authCoords = Settings.IMP.MAIN.AUTH_COORDS;
    VirtualWorld authWorld = this.factory.createVirtualWorld(
        Dimension.valueOf(Settings.IMP.MAIN.DIMENSION),
        authCoords.X, authCoords.Y, authCoords.Z,
        (float) authCoords.YAW, (float) authCoords.PITCH
    );

    if (Settings.IMP.MAIN.LOAD_WORLD) {
      try {
        Path path = this.dataDirectory.resolve(Settings.IMP.MAIN.WORLD_FILE_PATH);
        WorldFile file;
        switch (Settings.IMP.MAIN.WORLD_FILE_TYPE) {
          case "schematic": {
            file = new SchematicFile(path);
            break;
          }
          case "structure": {
            file = new StructureFile(path);
            break;
          }
          default: {
            LOGGER.error("Incorrect world file type.");
            this.server.shutdown();
            return;
          }
        }

        Settings.MAIN.WORLD_COORDS coords = Settings.IMP.MAIN.WORLD_COORDS;
        file.toWorld(this.factory, authWorld, coords.X, coords.Y, coords.Z, Settings.IMP.MAIN.WORLD_LIGHT_LEVEL);
      } catch (IOException e) {
        e.printStackTrace();
      }
    }

    if (this.authServer != null) {
      this.authServer.dispose();
    }

    this.authServer = this.factory
        .createLimbo(authWorld)
        .setName("LimboAuth")
        .setWorldTime(Settings.IMP.MAIN.WORLD_TICKS)
        .setGameMode(GameMode.valueOf(Settings.IMP.MAIN.GAME_MODE))
        .registerCommand(new LimboCommandMeta(this.filterCommands(Settings.IMP.MAIN.REGISTER_COMMAND)))
        .registerCommand(new LimboCommandMeta(this.filterCommands(Settings.IMP.MAIN.LOGIN_COMMAND)))
        .registerCommand(new LimboCommandMeta(this.filterCommands(Settings.IMP.MAIN.TOTP_COMMAND)));

    EventManager eventManager = this.server.getEventManager();
    eventManager.unregisterListeners(this);
    eventManager.register(this, new AuthListener(this, this.playerDao, this.floodgateApi));

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

    eventManager.fireAndForget(new AuthPluginReloadEvent());
  }

  private List<String> filterCommands(List<String> commands) {
    return commands.stream().filter(command -> command.startsWith("/")).map(command -> command.substring(1)).collect(Collectors.toList());
  }

  private void checkCache(Map<String, ? extends CachedUser> userMap, long time) {
    userMap.entrySet().stream()
        .filter(userEntry -> userEntry.getValue().getCheckTime() + time <= System.currentTimeMillis())
        .map(Map.Entry::getKey)
        .forEach(userMap::remove);
  }

  public void migrateDb(Dao<?, ?> dao) {
    TableInfo<?, ?> tableInfo = dao.getTableInfo();

    Set<FieldType> tables = new HashSet<>();
    Collections.addAll(tables, tableInfo.getFieldTypes());

    String findSql;
    String database = Settings.IMP.DATABASE.DATABASE;
    String tableName = tableInfo.getTableName();
    switch (Settings.IMP.DATABASE.STORAGE_TYPE) {
      case "h2": {
        findSql = "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = '" + tableName + "';";
        break;
      }
      case "postgresql": {
        findSql = "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_CATALOG = '" + database + "' AND TABLE_NAME = '" + tableName + "';";
        break;
      }
      case "mysql": {
        findSql = "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = '" + database + "' AND TABLE_NAME = '" + tableName + "';";
        break;
      }
      default: {
        LOGGER.error("WRONG DATABASE TYPE.");
        this.server.shutdown();
        return;
      }
    }

    try (GenericRawResults<String[]> queryResult = dao.queryRaw(findSql)) {
      queryResult.forEach(result -> tables.removeIf(table -> table.getColumnName().equalsIgnoreCase(result[0])));

      tables.forEach(table -> {
        try {
          StringBuilder builder = new StringBuilder("ALTER TABLE \"" + tableName + "\" ADD ");
          String columnDefinition = table.getColumnDefinition();
          DatabaseType databaseType = dao.getConnectionSource().getDatabaseType();
          if (columnDefinition == null) {
            List<String> dummy = List.of();
            databaseType.appendColumnArg(table.getTableName(), builder, table, dummy, dummy, dummy, dummy);
          } else {
            databaseType.appendEscapedEntityName(builder, table.getColumnName());
            builder.append(" ").append(columnDefinition).append(" ");
          }

          dao.executeRawNoArgs(builder.toString());
        } catch (SQLException e) {
          e.printStackTrace();
        }
      });
    } catch (Exception e) {
      e.printStackTrace();
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
    String nickname = player.getUsername();
    if (!this.nicknameValidationPattern.matcher((isFloodgate) ? nickname.substring(this.floodgateApi.getPrefixLength()) : nickname).matches()) {
      player.disconnect(this.nicknameInvalidKick);
      return;
    }

    RegisteredPlayer registeredPlayer = AuthSessionHandler.fetchInfo(this.playerDao, nickname);

    boolean onlineMode = player.isOnlineMode();
    TaskEvent.Result result = TaskEvent.Result.NORMAL;

    if (onlineMode || isFloodgate) {
      if (registeredPlayer == null || registeredPlayer.getHash().isEmpty()) {
        RegisteredPlayer nicknameRegisteredPlayer = registeredPlayer;
        registeredPlayer = AuthSessionHandler.fetchInfo(this.playerDao, player.getUniqueId());

        if (nicknameRegisteredPlayer != null && registeredPlayer == null && nicknameRegisteredPlayer.getHash().isEmpty()) {
          registeredPlayer = nicknameRegisteredPlayer;
          registeredPlayer.setPremiumUuid(player.getUniqueId().toString());
          try {
            this.playerDao.update(registeredPlayer);
          } catch (SQLException e) {
            e.printStackTrace();
          }
        }

        if (nicknameRegisteredPlayer == null && registeredPlayer == null && Settings.IMP.MAIN.SAVE_PREMIUM_ACCOUNTS) {
          registeredPlayer = new RegisteredPlayer(
              nickname,
              nickname.toLowerCase(Locale.ROOT),
              "",
              player.getRemoteAddress().getAddress().getHostAddress(),
              "",
              System.currentTimeMillis(),
              player.getUniqueId().toString(),
              player.getUniqueId().toString()
          );

          try {
            this.playerDao.create(registeredPlayer);
          } catch (SQLException e) {
            e.printStackTrace();
          }
        }

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
      }
    }

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
        try {
          this.authServer.spawnPlayer(player, new AuthSessionHandler(this.playerDao, player, this, registeredPlayer));
        } catch (Throwable t) {
          t.printStackTrace();
        }

        break;
      }
    }
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

  public PremiumResponse isPremiumExternal(String nickname) {
    try {
      HttpResponse<String> response = this.client.send(
          HttpRequest.newBuilder()
              .uri(URI.create(String.format(Settings.IMP.MAIN.ISPREMIUM_AUTH_URL, URLEncoder.encode(nickname, StandardCharsets.UTF_8))))
              .build(),
          HttpResponse.BodyHandlers.ofString()
      );

      int statusCode = response.statusCode();

      if (statusCode == Settings.IMP.MAIN.STATUS_CODE_RATE_LIMIT) {
        return new PremiumResponse(PremiumState.RATE_LIMIT);
      }

      JsonElement jsonElement = JsonParser.parseString(response.body());

      if (statusCode == Settings.IMP.MAIN.STATUS_CODE_USER_EXISTS
          && this.validateScheme(jsonElement, Settings.IMP.MAIN.USER_EXISTS_JSON_VALIDATOR_FIELDS)) {
        return new PremiumResponse(PremiumState.PREMIUM, ((JsonObject) jsonElement).get(Settings.IMP.MAIN.JSON_UUID_FIELD).getAsString());
      }

      if (statusCode == Settings.IMP.MAIN.STATUS_CODE_USER_NOT_EXISTS
          && this.validateScheme(jsonElement, Settings.IMP.MAIN.USER_NOT_EXISTS_JSON_VALIDATOR_FIELDS)) {
        return new PremiumResponse(PremiumState.CRACKED);
      }

      return new PremiumResponse(PremiumState.ERROR);
    } catch (IOException | InterruptedException e) {
      LOGGER.error("Unable to authenticate with Mojang.", e);
      return new PremiumResponse(PremiumState.ERROR);
    }
  }

  public PremiumResponse isPremiumInternal(String nickname) {
    try {
      QueryBuilder<RegisteredPlayer, String> crackedCountQuery = this.playerDao.queryBuilder();
      crackedCountQuery.where()
          .eq(RegisteredPlayer.LOWERCASE_NICKNAME_FIELD, nickname)
          .and()
          .ne(RegisteredPlayer.HASH_FIELD, "");
      crackedCountQuery.setCountOf(true);

      QueryBuilder<RegisteredPlayer, String> premiumCountQuery = this.playerDao.queryBuilder();
      premiumCountQuery.where()
          .eq(RegisteredPlayer.LOWERCASE_NICKNAME_FIELD, nickname)
          .and()
          .eq(RegisteredPlayer.HASH_FIELD, "");
      premiumCountQuery.setCountOf(true);

      if (this.playerDao.countOf(crackedCountQuery.prepare()) != 0) {
        return new PremiumResponse(PremiumState.CRACKED);
      }

      if (this.playerDao.countOf(premiumCountQuery.prepare()) != 0) {
        return new PremiumResponse(PremiumState.PREMIUM);
      }

      return new PremiumResponse(PremiumState.UNKNOWN);
    } catch (SQLException e) {
      LOGGER.error("Unable to check if account is premium.", e);
      return new PremiumResponse(PremiumState.ERROR);
    }
  }

  public boolean isPremiumUuid(UUID uuid) {
    try {
      QueryBuilder<RegisteredPlayer, String> premiumCountQuery = this.playerDao.queryBuilder();
      premiumCountQuery.where()
          .eq(RegisteredPlayer.PREMIUM_UUID_FIELD, uuid.toString())
          .and()
          .eq(RegisteredPlayer.HASH_FIELD, "");
      premiumCountQuery.setCountOf(true);

      return this.playerDao.countOf(premiumCountQuery.prepare()) != 0;
    } catch (SQLException e) {
      LOGGER.error("Unable to check if account is premium.", e);
      return false;
    }
  }

  @SafeVarargs
  private boolean checkIsPremiumAndCache(String nickname, Function<String, PremiumResponse>... functions) {
    String lowercaseNickname = nickname.toLowerCase(Locale.ROOT);
    if (this.premiumCache.containsKey(lowercaseNickname)) {
      return this.premiumCache.get(lowercaseNickname).isPremium();
    }

    boolean premium = false;
    boolean unknown = false;
    boolean wasRateLimited = false;
    boolean wasError = false;
    UUID uuid = null;

    for (Function<String, PremiumResponse> function : functions) {
      PremiumResponse check = function.apply(lowercaseNickname);
      if (check.getUuid() != null) {
        uuid = check.getUuid();
      }

      switch (check.getState()) {
        case CRACKED: {
          this.premiumCache.put(lowercaseNickname, new CachedPremiumUser(System.currentTimeMillis(), false));
          return false;
        }
        case PREMIUM: {
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
    }

    if (wasRateLimited && unknown || wasRateLimited && wasError) {
      return Settings.IMP.MAIN.ON_RATE_LIMIT_PREMIUM;
    }

    if (wasError && unknown || !premium) {
      return Settings.IMP.MAIN.ON_SERVER_ERROR_PREMIUM;
    }

    if (unknown) {
      if (uuid != null && this.isPremiumUuid(uuid)) {
        this.premiumCache.put(lowercaseNickname, new CachedPremiumUser(System.currentTimeMillis(), true));
        return true;
      }

      if (Settings.IMP.MAIN.ONLINE_MODE_NEED_AUTH) {
        return false;
      }
    }

    this.premiumCache.put(lowercaseNickname, new CachedPremiumUser(System.currentTimeMillis(), true));
    return true;
  }

  public boolean isPremium(String nickname) {
    if (Settings.IMP.MAIN.FORCE_OFFLINE_MODE) {
      return false;
    } else {
      if (Settings.IMP.MAIN.CHECK_PREMIUM_PRIORITY_INTERNAL) {
        return checkIsPremiumAndCache(nickname, this::isPremiumInternal, this::isPremiumExternal);
      } else {
        return checkIsPremiumAndCache(nickname, this::isPremiumExternal, this::isPremiumInternal);
      }
    }
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

  public JdbcPooledConnectionSource getConnectionSource() {
    return this.connectionSource;
  }

  public Dao<RegisteredPlayer, String> getPlayerDao() {
    return this.playerDao;
  }

  static {
    // requireNonNull prevents the shade plugin from excluding the drivers in minimized jar.
    Objects.requireNonNull(com.mysql.cj.jdbc.Driver.class);
    Objects.requireNonNull(com.mysql.cj.conf.url.SingleConnectionUrl.class);

    Objects.requireNonNull(org.h2.Driver.class);
    Objects.requireNonNull(org.h2.engine.Engine.class);

    Objects.requireNonNull(org.postgresql.Driver.class);
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

  public static class PremiumResponse {

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
    PREMIUM(),
    CRACKED(),
    UNKNOWN(),
    RATE_LIMIT(),
    ERROR()
  }
}
