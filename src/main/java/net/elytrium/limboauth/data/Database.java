package net.elytrium.limboauth.data;

import com.zaxxer.hikari.HikariConfig;
import com.zaxxer.hikari.HikariDataSource;
import java.sql.DriverManager;
import java.sql.SQLException;
import net.elytrium.limboauth.BuildConfig;
import net.elytrium.limboauth.LimboAuth;
import net.elytrium.limboauth.Settings;
import net.elytrium.limboauth.utils.LibrariesLoader;
import org.jooq.CloseableDSLContext;
import org.jooq.ExecuteContext;
import org.jooq.ExecuteListener;
import org.jooq.Field;
import org.jooq.impl.DefaultDSLContext;

public class Database extends DefaultDSLContext implements CloseableDSLContext {

  private final ExecuteListener listener = new ExecuteListener() {

    @Override
    public void exception(ExecuteContext ctx) {
      Database.this.plugin.getLogger().error("An unexpected internal error was caught during the database SQL operations.", ctx.exception());
    }
  };
  private final LimboAuth plugin;
  private final HikariDataSource dataSource;

  public Database(LimboAuth plugin) throws Throwable {
    this(Database.createDataSource(plugin), plugin);
  }

  private Database(HikariDataSource dataSource, LimboAuth plugin) {
    super(dataSource, Settings.DATABASE.storageType.sqlDialect);
    this.plugin = plugin;
    this.dataSource = dataSource;

    this.configuration().set(plugin::getExecutor);
    this.configuration().set(() -> this.listener);

    Field<?>[] fields = PlayerData.Table.INSTANCE.fields();
    this.createTableIfNotExists(PlayerData.Table.INSTANCE).columns(fields).execute();
    for (Field<?> field : fields) {
      this.alterTable(PlayerData.Table.INSTANCE).addColumnIfNotExists(field).execute();
    }
  }

  @Override
  public void close() {
    this.dataSource.close();
  }

  static {
    System.setProperty("org.jooq.no-logo", "true");
    System.setProperty("org.jooq.no-tips", "true");
  }

  private static HikariDataSource createDataSource(LimboAuth plugin) throws Throwable {
    LibrariesLoader.resolveAndLoad(plugin, plugin.getLogger(), plugin.getServer(), BuildConfig.REPOSITORIES, Settings.DATABASE.storageType.libraries);

    HikariConfig config = new HikariConfig();
    config.setPoolName("limboauth-db-pool");

    config.setDriverClassName(Settings.DATABASE.storageType.driverClassName);

    config.setJdbcUrl(Settings.DATABASE.storageType.jdbcUrl.apply(plugin.getDataDirectory()));
    // TODO h2 non pass -> pass
    config.setUsername(Settings.DATABASE.username);
    config.setPassword(Settings.DATABASE.password);

    config.setConnectionTimeout(Settings.DATABASE.connectionTimeout);
    config.setMaxLifetime(Settings.DATABASE.maxLifetime);
    config.setMaximumPoolSize(Settings.DATABASE.maximumPoolSize);
    config.setMinimumIdle(Settings.DATABASE.minimumIdle);
    config.setKeepaliveTime(Settings.DATABASE.keepaliveTime);

    Settings.DATABASE.connectionParameters.forEach(config::addDataSourceProperty);

    try {
      return new HikariDataSource(config);
    } finally {
      DriverManager.getDrivers().asIterator().forEachRemaining(driver -> {
        if (Settings.DATABASE.storageType.driverClassName.equals(driver.getClass().getName())) {
          try {
            DriverManager.deregisterDriver(driver);
          } catch (SQLException e) {
            // ignore
          }
        }
      });
    }
  }
}
