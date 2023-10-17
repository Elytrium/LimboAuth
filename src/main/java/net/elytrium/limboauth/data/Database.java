package net.elytrium.limboauth.data;

import com.zaxxer.hikari.HikariConfig;
import com.zaxxer.hikari.HikariDataSource;
import java.sql.DriverManager;
import java.sql.SQLException;
import net.elytrium.limboauth.LimboAuth;
import net.elytrium.limboauth.Settings;
import net.elytrium.limboauth.utils.LibrariesLoader;
import org.jooq.DSLContext;
import org.jooq.Field;
import org.jooq.impl.DSL;

public class Database {

  private final LimboAuth plugin;
  private final HikariDataSource dataSource;
  private final DSLContext context;

  public Database(LimboAuth plugin) throws Throwable {
    this.plugin = plugin;

    DataSource storageType = Settings.DATABASE.storageType;
    LibrariesLoader.resolveAndLoad(plugin, plugin.getLogger(), plugin.getServer(), storageType.libraries);

    HikariConfig config = new HikariConfig();
    config.setPoolName("limboauth-db-pool");

    config.setDriverClassName(Settings.DATABASE.storageType.driverClassName);

    config.setJdbcUrl(storageType.jdbcUrl.apply(plugin.getDataDirectory()));
    // TODO h2 non pass -> pass
    config.setUsername(Settings.DATABASE.username);
    config.setPassword(Settings.DATABASE.password);

    config.setConnectionTimeout(Settings.DATABASE.connectionTimeout);
    config.setMaxLifetime(Settings.DATABASE.maxLifetime);
    config.setMaximumPoolSize(Settings.DATABASE.maximumPoolSize);
    config.setMinimumIdle(Settings.DATABASE.minimumIdle);
    config.setKeepaliveTime(Settings.DATABASE.keepaliveTime);

    Settings.DATABASE.connectionParameters.forEach(config::addDataSourceProperty);

    this.dataSource = new HikariDataSource(config);
    this.context = DSL.using(this.dataSource, storageType.sqlDialect);

    DriverManager.getDrivers().asIterator().forEachRemaining(driver -> {
      if (driver.getClass().getName().equals(storageType.driverClassName)) {
        try {
          DriverManager.deregisterDriver(driver);
        } catch (SQLException e) {
          // ignore
        }
      }
    });

    this.context.configuration().set(plugin::getExecutor);
    Field<?>[] fields = PlayerData.Table.INSTANCE.fields();
    this.context.createTableIfNotExists(PlayerData.Table.INSTANCE).columns(fields).execute();
    for (Field<?> field : fields) {
      this.context.alterTable(PlayerData.Table.INSTANCE).addColumnIfNotExists(field).execute();
    }
  }

  public void shutdown() {
    this.dataSource.close();
  }

  public <T> T handleSqlError(Throwable t) {
    this.plugin.getLogger().error("An unexpected internal error was caught during the database SQL operations.", t);
    return null;
  }

  public DSLContext getContext() {
    return this.context;
  }

  static {
    System.setProperty("org.jooq.no-logo", "true");
    System.setProperty("org.jooq.no-tips", "true");
  }
}
