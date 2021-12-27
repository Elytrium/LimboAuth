/*
 * Copyright (C) 2021 Elytrium
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

import com.google.inject.Inject;
import com.google.inject.name.Named;
import com.j256.ormlite.dao.Dao;
import com.j256.ormlite.dao.DaoManager;
import com.j256.ormlite.field.FieldType;
import com.j256.ormlite.jdbc.JdbcPooledConnectionSource;
import com.j256.ormlite.stmt.QueryBuilder;
import com.j256.ormlite.table.TableUtils;
import com.velocitypowered.api.command.CommandManager;
import com.velocitypowered.api.event.Subscribe;
import com.velocitypowered.api.event.proxy.ProxyInitializeEvent;
import com.velocitypowered.api.plugin.Dependency;
import com.velocitypowered.api.plugin.Plugin;
import com.velocitypowered.api.plugin.PluginContainer;
import com.velocitypowered.api.plugin.annotation.DataDirectory;
import com.velocitypowered.api.proxy.Player;
import com.velocitypowered.api.proxy.ProxyServer;
import edu.umd.cs.findbugs.annotations.SuppressFBWarnings;
import java.io.File;
import java.io.IOException;
import java.net.InetAddress;
import java.net.URI;
import java.net.http.HttpClient;
import java.net.http.HttpRequest;
import java.net.http.HttpResponse;
import java.nio.file.Files;
import java.nio.file.Path;
import java.nio.file.Paths;
import java.sql.SQLException;
import java.util.ArrayList;
import java.util.Collections;
import java.util.HashSet;
import java.util.List;
import java.util.Locale;
import java.util.Map;
import java.util.Objects;
import java.util.Set;
import java.util.concurrent.ConcurrentHashMap;
import java.util.concurrent.Executors;
import java.util.concurrent.TimeUnit;
import java.util.regex.Pattern;
import java.util.stream.Collectors;
import net.elytrium.limboapi.api.Limbo;
import net.elytrium.limboapi.api.LimboFactory;
import net.elytrium.limboapi.api.chunk.Dimension;
import net.elytrium.limboapi.api.chunk.VirtualWorld;
import net.elytrium.limboapi.api.file.SchematicFile;
import net.elytrium.limboapi.api.file.WorldFile;
import net.elytrium.limboauth.command.ChangePasswordCommand;
import net.elytrium.limboauth.command.DestroySessionCommand;
import net.elytrium.limboauth.command.ForceChangePasswordCommand;
import net.elytrium.limboauth.command.ForceUnregisterCommand;
import net.elytrium.limboauth.command.LimboAuthCommand;
import net.elytrium.limboauth.command.PremiumCommand;
import net.elytrium.limboauth.command.TotpCommand;
import net.elytrium.limboauth.command.UnregisterCommand;
import net.elytrium.limboauth.handler.AuthSessionHandler;
import net.elytrium.limboauth.listener.AuthListener;
import net.elytrium.limboauth.model.RegisteredPlayer;
import net.elytrium.limboauth.utils.UpdatesChecker;
import net.kyori.adventure.text.Component;
import net.kyori.adventure.text.serializer.legacy.LegacyComponentSerializer;
import org.bstats.velocity.Metrics;
import org.slf4j.Logger;

@Plugin(
    id = "limboauth",
    name = "LimboAuth",
    version = BuildConstants.AUTH_VERSION,
    url = "https://elytrium.net/",
    authors = {"hevav", "mdxd44"},
    dependencies = {@Dependency(id = "limboapi")}
)
@SuppressFBWarnings({"EI_EXPOSE_REP", "MS_EXPOSE_REP"})
public class LimboAuth {

  private static LimboAuth instance;

  private final HttpClient client = HttpClient.newHttpClient();
  private final ProxyServer server;
  private final Logger logger;
  private final Metrics.Factory metricsFactory;
  private final Path dataDirectory;
  private final LimboFactory factory;

  private final Set<String> unsafePasswords = new HashSet<>();
  private Map<String, CachedUser> cachedAuthChecks;
  private Dao<RegisteredPlayer, String> playerDao;
  private Pattern nicknameValidationPattern;
  private Limbo authServer;
  private Component nicknameInvalid;

  @Inject
  @SuppressWarnings("OptionalGetWithoutIsPresent")
  public LimboAuth(ProxyServer server, Logger logger, Metrics.Factory metricsFactory,
      @Named("limboapi") PluginContainer factory, @DataDirectory Path dataDirectory) {
    setInstance(this);

    this.server = server;
    this.logger = logger;
    this.metricsFactory = metricsFactory;
    this.dataDirectory = dataDirectory;
    this.factory = (LimboFactory) factory.getInstance().get();
  }

  @Subscribe
  public void onProxyInitialization(ProxyInitializeEvent event) throws Exception {
    this.metricsFactory.make(this, 13700);
    System.setProperty("com.j256.simplelogging.level", "ERROR");

    this.reload();

    UpdatesChecker.checkForUpdates(this.getLogger());
  }

  @SuppressWarnings("SwitchStatementWithTooFewBranches")
  public void reload() throws Exception {
    Settings.IMP.reload(new File(this.dataDirectory.toFile().getAbsoluteFile(), "config.yml"));

    if (Settings.IMP.MAIN.CHECK_PASSWORD_STRENGTH) {
      this.unsafePasswords.clear();
      Path unsafePasswordsFile = Paths.get(this.dataDirectory.toFile().getAbsolutePath(), Settings.IMP.MAIN.UNSAFE_PASSWORDS_FILE);
      if (!unsafePasswordsFile.toFile().exists()) {
        Files.copy(Objects.requireNonNull(this.getClass().getResourceAsStream("/unsafe_passwords.txt")), unsafePasswordsFile);
      }

      this.unsafePasswords.addAll(Files.lines(unsafePasswordsFile).collect(Collectors.toSet()));
    }

    this.cachedAuthChecks = new ConcurrentHashMap<>();

    Settings.DATABASE dbConfig = Settings.IMP.DATABASE;
    JdbcPooledConnectionSource connectionSource;
    // requireNonNull prevents the shade plugin from excluding the drivers in minimized jar.
    switch (dbConfig.STORAGE_TYPE.toLowerCase(Locale.ROOT)) {
      case "h2": {
        Objects.requireNonNull(org.h2.Driver.class);
        Objects.requireNonNull(org.h2.engine.Engine.class);
        connectionSource = new JdbcPooledConnectionSource("jdbc:h2:" + this.dataDirectory.toFile().getAbsoluteFile() + "/limboauth");
        break;
      }
      case "mysql": {
        Objects.requireNonNull(com.mysql.cj.jdbc.Driver.class);
        Objects.requireNonNull(com.mysql.cj.conf.url.SingleConnectionUrl.class);
        connectionSource = new JdbcPooledConnectionSource(
            "jdbc:mysql://" + dbConfig.HOSTNAME + "/" + dbConfig.DATABASE + dbConfig.CONNECTION_PARAMETERS, dbConfig.USER, dbConfig.PASSWORD
        );
        break;
      }
      case "postgresql": {
        Objects.requireNonNull(org.postgresql.Driver.class);
        connectionSource = new JdbcPooledConnectionSource(
            "jdbc:postgresql://" + dbConfig.HOSTNAME + "/" + dbConfig.DATABASE + dbConfig.CONNECTION_PARAMETERS, dbConfig.USER, dbConfig.PASSWORD
        );
        break;
      }
      default: {
        this.getLogger().error("WRONG DATABASE TYPE.");
        this.server.shutdown();
        return;
      }
    }

    TableUtils.createTableIfNotExists(connectionSource, RegisteredPlayer.class);
    this.playerDao = DaoManager.createDao(connectionSource, RegisteredPlayer.class);
    this.nicknameValidationPattern = Pattern.compile(Settings.IMP.MAIN.ALLOWED_NICKNAME_REGEX);

    this.migrateDb(this.playerDao);

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
    manager.register("premium", new PremiumCommand(this, this.playerDao));
    manager.register("forceunregister", new ForceUnregisterCommand(this, this.server, this.playerDao), "forceunreg");
    manager.register("changepassword", new ChangePasswordCommand(this.playerDao), "changepass");
    manager.register("forcechangepassword", new ForceChangePasswordCommand(this.server, this.playerDao), "forcechangepass");
    manager.register("destroysession", new DestroySessionCommand(this));
    if (Settings.IMP.MAIN.ENABLE_TOTP) {
      manager.register("2fa", new TotpCommand(this.playerDao), "totp");
    }
    manager.register("limboauth", new LimboAuthCommand(), "la", "auth", "lauth");

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
          default: {
            this.getLogger().error("Incorrect world file type.");
            this.server.shutdown();
            return;
          }
        }

        Settings.MAIN.WORLD_COORDS coords = Settings.IMP.MAIN.WORLD_COORDS;
        file.toWorld(this.factory, authWorld, coords.X, coords.Y, coords.Z);
      } catch (IOException e) {
        e.printStackTrace();
      }
    }

    this.authServer = this.factory.createLimbo(authWorld);

    this.nicknameInvalid = LegacyComponentSerializer.legacyAmpersand().deserialize(Settings.IMP.MAIN.STRINGS.NICKNAME_INVALID_KICK);

    this.server.getEventManager().unregisterListeners(this);
    this.server.getEventManager().register(this, new AuthListener(this.playerDao));

    Executors.newScheduledThreadPool(1, task -> new Thread(task, "purge-cache")).scheduleAtFixedRate(() ->
        this.checkCache(this.cachedAuthChecks, Settings.IMP.MAIN.PURGE_CACHE_MILLIS),
        Settings.IMP.MAIN.PURGE_CACHE_MILLIS,
        Settings.IMP.MAIN.PURGE_CACHE_MILLIS,
        TimeUnit.MILLISECONDS
    );
  }

  public void migrateDb(Dao<RegisteredPlayer, String> playerDao) {
    Set<FieldType> tables = new HashSet<>();
    Collections.addAll(tables, playerDao.getTableInfo().getFieldTypes());

    String findSql;
    switch (Settings.IMP.DATABASE.STORAGE_TYPE) {
      case "h2": {
        findSql = "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = '"
            + playerDao.getTableInfo().getTableName() + "';";
        break;
      }
      case "postgresql":
      case "mysql": {
        findSql = "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = '" + Settings.IMP.DATABASE.DATABASE
            + "' AND TABLE_NAME = '" + playerDao.getTableInfo().getTableName() + "';";
        break;
      }
      default: {
        this.getLogger().error("WRONG DATABASE TYPE.");
        this.server.shutdown();
        return;
      }
    }

    try {
      playerDao.queryRaw(findSql).forEach(e -> tables.removeIf(q -> q.getColumnName().equalsIgnoreCase(e[0])));

      tables.forEach(t -> {
        try {
          String columnDefinition = t.getColumnDefinition();
          StringBuilder builder = new StringBuilder("ALTER TABLE `auth` ADD ");
          List<String> dummy = new ArrayList<>();
          if (columnDefinition == null) {
            playerDao.getConnectionSource().getDatabaseType().appendColumnArg(t.getTableName(), builder, t, dummy, dummy, dummy, dummy);
          } else {
            playerDao.getConnectionSource().getDatabaseType().appendEscapedEntityName(builder, t.getColumnName());
            builder.append(" ").append(columnDefinition).append(" ");
          }

          playerDao.executeRawNoArgs(builder.toString());
        } catch (SQLException e) {
          e.printStackTrace();
        }
      });
    } catch (SQLException e) {
      e.printStackTrace();
    }
  }

  public void cacheAuthUser(Player player) {
    String username = player.getUsername();
    this.cachedAuthChecks.remove(username);
    this.cachedAuthChecks.put(username, new CachedUser(player.getRemoteAddress().getAddress(), System.currentTimeMillis()));
  }

  public void removePlayerFromCache(String username) {
    this.cachedAuthChecks.remove(username);
  }

  public boolean needAuth(Player player) {
    String username = player.getUsername();

    if (!this.cachedAuthChecks.containsKey(username)) {
      return true;
    }

    return !this.cachedAuthChecks.get(username).getInetAddress().equals(player.getRemoteAddress().getAddress());
  }

  public void authPlayer(Player player) {
    String nickname = player.getUsername();
    if (!this.nicknameValidationPattern.matcher(nickname).matches()) {
      player.disconnect(this.nicknameInvalid);
      return;
    }

    RegisteredPlayer registeredPlayer = AuthSessionHandler.fetchInfo(this.playerDao, nickname);
    if (player.isOnlineMode()) {
      if (registeredPlayer == null || registeredPlayer.getHash().isEmpty()) {
        registeredPlayer = AuthSessionHandler.fetchInfo(this.playerDao, player.getUniqueId());
        if (registeredPlayer == null || registeredPlayer.getHash().isEmpty()) {
          this.factory.passLoginLimbo(player);
          return;
        }
      }
    }

    // Send player to auth virtual server.
    try {
      this.authServer.spawnPlayer(player, new AuthSessionHandler(this.playerDao, player, this, registeredPlayer));
    } catch (Throwable t) {
      this.getLogger().error("Error", t);
    }
  }

  public boolean isPremiumExternal(String nickname) {
    try {
      return this.client.send(
          HttpRequest.newBuilder()
              .uri(URI.create(String.format(Settings.IMP.MAIN.ISPREMIUM_AUTH_URL, nickname)))
              .build(),
          HttpResponse.BodyHandlers.ofString()
      ).statusCode() == 200;
    } catch (IOException | InterruptedException e) {
      this.getLogger().error("Unable to authenticate with Mojang", e);
      return true;
    }
  }

  public boolean isPremium(String nickname) {
    try {
      if (this.isPremiumExternal(nickname)) {
        QueryBuilder<RegisteredPlayer, String> query = this.playerDao.queryBuilder();
        query.where()
            .eq("LOWERCASENICKNAME", nickname.toLowerCase(Locale.ROOT))
            .and()
            .ne("HASH", "");
        query.setCountOf(true);
        QueryBuilder<RegisteredPlayer, String> query2 = this.playerDao.queryBuilder();
        query2.where()
            .eq("LOWERCASENICKNAME", nickname.toLowerCase(Locale.ROOT))
            .and()
            .eq("HASH", "");
        query2.setCountOf(true);
        if (Settings.IMP.MAIN.ONLINE_MODE_NEED_AUTH) {
          return this.playerDao.countOf(query.prepare()) == 0
              && this.playerDao.countOf(query2.prepare()) != 0;
        } else {
          return this.playerDao.countOf(query.prepare()) == 0;
        }
      } else {
        return false;
      }
    } catch (SQLException e) {
      this.getLogger().error("Unable to authenticate with Mojang", e);
      return true;
    }
  }

  private void checkCache(Map<String, CachedUser> userMap, long time) {
    userMap.entrySet().stream()
        .filter(u -> u.getValue().getCheckTime() + time <= System.currentTimeMillis())
        .map(Map.Entry::getKey)
        .forEach(userMap::remove);
  }

  private static void setInstance(LimboAuth instance) {
    LimboAuth.instance = instance;
  }

  public static LimboAuth getInstance() {
    return instance;
  }

  public Set<String> getUnsafePasswords() {
    return this.unsafePasswords;
  }

  public Logger getLogger() {
    return this.logger;
  }

  public ProxyServer getServer() {
    return this.server;
  }

  private static class CachedUser {

    private final InetAddress inetAddress;
    private final long checkTime;

    public CachedUser(InetAddress inetAddress, long checkTime) {
      this.inetAddress = inetAddress;
      this.checkTime = checkTime;
    }

    public InetAddress getInetAddress() {
      return this.inetAddress;
    }

    public long getCheckTime() {
      return this.checkTime;
    }
  }
}
