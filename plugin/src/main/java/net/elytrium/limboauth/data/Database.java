/*
 * Copyright (C) 2023-2024 Elytrium
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

package net.elytrium.limboauth.data;

import com.zaxxer.hikari.HikariConfig;
import com.zaxxer.hikari.HikariDataSource;
import java.sql.DriverManager;
import java.sql.SQLException;
import java.util.concurrent.CompletionStage;
import net.elytrium.limboauth.BuildConfig;
import net.elytrium.limboauth.LimboAuth;
import net.elytrium.limboauth.Settings;
import net.elytrium.limboauth.utils.LibLoader;
import org.jooq.CloseableDSLContext;
import org.jooq.Condition;
import org.jooq.Configuration;
import org.jooq.ExecuteContext;
import org.jooq.ExecuteListener;
import org.jooq.Field;
import org.jooq.Record1;
import org.jooq.Result;
import org.jooq.Table;
import org.jooq.impl.DSL;
import org.jooq.impl.DefaultDSLContext;

public final class Database extends DefaultDSLContext implements CloseableDSLContext { // TODO more limit() and fetchExistsAsync

  private static Database INSTANCE;

  private final HikariDataSource dataSource;

  private Database(LimboAuth plugin) {
    this(Database.createDataSource(plugin));
  }

  private Database(HikariDataSource dataSource) {
    super(dataSource, Settings.DATABASE.storageType.sqlDialect);
    this.dataSource = dataSource;

    Field<?>[] fields = PlayerData.Table.INSTANCE.fields();
    this.createTableIfNotExists(PlayerData.Table.INSTANCE).columns(fields).primaryKey(PlayerData.Table.LOWERCASE_NICKNAME_FIELD).execute();
    // TODO configurable indexes?
    this.createIndexIfNotExists("AUTH_PREMIUMUUID_idx").on(PlayerData.Table.INSTANCE).include(PlayerData.Table.PREMIUM_UUID_FIELD).execute();
    this.createIndexIfNotExists("AUTH_IP_idx").on(PlayerData.Table.INSTANCE).include(PlayerData.Table.IP_FIELD).execute();
    for (Field<?> field : fields) {
      this.alterTable(PlayerData.Table.INSTANCE).addColumnIfNotExists(field).execute();
    }
  }

  @Override
  public void close() {
    this.dataSource.close();
  }

  public CompletionStage<Result<Record1<Boolean>>> fetchExistsAsync(Table<?> table, Condition condition) {
    return this.select(DSL.exists(this.selectOne().from(table).where(condition).limit(1))).limit(1).fetchAsync();
  }

  static {
    System.setProperty("org.jooq.no-logo", "true");
    System.setProperty("org.jooq.no-tips", "true");
    System.setProperty("org.jooq.log.org.jooq.impl.DefaultExecuteContext.logVersionSupport", "ERROR");
  }

  public static Database get() {
    return Database.INSTANCE;
  }

  public static void configure(LimboAuth plugin) {
    Configuration configuration = (Database.INSTANCE = new Database(plugin)).configuration();

    configuration.set(plugin::getExecutor);

    ExecuteListener listener = new ExecuteListener() {

      @Override
      public void exception(ExecuteContext ctx) {
        LimboAuth.LOGGER.error("An unexpected internal error was caught during the database SQL operations", ctx.exception());
      }
    };
    configuration.set(() -> listener);
  }

  // Inspired luckperms' implementation (HikariConnectionFactory and it's subclasses (DriverBasedHikariConnectionFactory, MySqlConnectionFactory, etc.))
  // https://github.com/LuckPerms/LuckPerms/blob/f86585c3ccc7fe74fbb3b1d0251d93f263d70fe1/common/src/main/java/me/lucko/luckperms/common/storage/implementation/sql/connection/hikari/HikariConnectionFactory.java
  private static HikariDataSource createDataSource(LimboAuth plugin) {
    try {
      LibLoader.resolveAndLoad(plugin.getLoader(), plugin.getServer(), LimboAuth.LOGGER, BuildConfig.REPOSITORIES, Settings.DATABASE.storageType.libraries);
    } catch (Throwable e) {
      throw new RuntimeException(e);
    }

    HikariConfig config = new HikariConfig();
    config.setPoolName("limboauth-pool");

    String driver = Settings.DATABASE.storageType.sqlDialect.thirdParty().driver();
    config.setDriverClassName(driver);

    {
      config.setJdbcUrl(Settings.DATABASE.storageType.getJdbcUrl(plugin.getDataDirectory()));
      config.setUsername(Settings.DATABASE.username);
      config.setPassword(Settings.DATABASE.password);

      Settings.DATABASE.connectionParameters.forEach(config::addDataSourceProperty);

      config.setConnectionTimeout(Settings.DATABASE.connectionTimeout);
      config.setMaxLifetime(Settings.DATABASE.maxLifetime);
      config.setMaximumPoolSize(Settings.DATABASE.maximumPoolSize);
      config.setMinimumIdle(Settings.DATABASE.minimumIdle);
      config.setKeepaliveTime(Settings.DATABASE.keepaliveTime);

      config.setInitializationFailTimeout(-1);
    }

    try {
      return new HikariDataSource(config);
    } finally {
      DriverManager.getDrivers().asIterator().forEachRemaining(next -> {
        if (driver.equals(next.getClass().getName())) {
          try {
            DriverManager.deregisterDriver(next);
          } catch (SQLException e) {
            //e.printStackTrace();
          }
        }
      });
    }
  }
}
