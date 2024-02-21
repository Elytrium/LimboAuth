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

package net.elytrium.limboauth.data;

import com.zaxxer.hikari.HikariConfig;
import com.zaxxer.hikari.HikariDataSource;
import java.sql.DriverManager;
import java.sql.SQLException;
import java.util.concurrent.CompletionStage;
import net.elytrium.limboauth.BuildConfig;
import net.elytrium.limboauth.LimboAuth;
import net.elytrium.limboauth.Settings;
import net.elytrium.limboauth.utils.LibrariesLoader;
import org.apache.logging.log4j.Level;
import org.apache.logging.log4j.core.LoggerContext;
import org.jooq.CloseableDSLContext;
import org.jooq.Condition;
import org.jooq.ExecuteContext;
import org.jooq.ExecuteListener;
import org.jooq.Field;
import org.jooq.Record1;
import org.jooq.Result;
import org.jooq.Table;
import org.jooq.impl.DSL;
import org.jooq.impl.DefaultDSLContext;

public class Database extends DefaultDSLContext implements CloseableDSLContext { // TODO more limit() and fetchExistsAsync

  private final HikariDataSource dataSource;

  public Database(LimboAuth plugin) throws Throwable {
    this(plugin, Database.createDataSource(plugin));
  }

  private Database(LimboAuth plugin, HikariDataSource dataSource) {
    super(dataSource, Settings.DATABASE.storageType.sqlDialect);
    this.dataSource = dataSource;

    this.configuration().set(plugin::getExecutor);

    ExecuteListener listener = new ExecuteListener() {

      @Override
      public void exception(ExecuteContext ctx) {
        plugin.getLogger().error("An unexpected internal error was caught during the database SQL operations", ctx.exception());
      }
    };
    this.configuration().set(() -> listener);

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
    LoggerContext.getContext(false).getLogger("org.jooq.impl.DefaultExecuteContext.logVersionSupport").setLevel(Level.OFF);
  }

  private static HikariDataSource createDataSource(LimboAuth plugin) throws Throwable {
    LibrariesLoader.resolveAndLoad(plugin, plugin.getLogger(), plugin.getServer(), BuildConfig.REPOSITORIES, Settings.DATABASE.storageType.libraries);

    HikariConfig config = new HikariConfig();
    config.setPoolName("limboauth-pool");

    config.setDriverClassName(Settings.DATABASE.storageType.driverClassName);

    config.setJdbcUrl(Settings.DATABASE.storageType.jdbcUrl.apply(plugin.getDataDirectory()));
    config.setUsername(Settings.DATABASE.username);
    config.setPassword(Settings.DATABASE.password);

    config.setConnectionTimeout(Settings.DATABASE.connectionTimeout);
    config.setMaxLifetime(Settings.DATABASE.maxLifetime);
    config.setMaximumPoolSize(Settings.DATABASE.maximumPoolSize);
    config.setMinimumIdle(Settings.DATABASE.minimumIdle);
    config.setKeepaliveTime(Settings.DATABASE.keepaliveTime);

    config.setInitializationFailTimeout(-1);

    Settings.DATABASE.connectionParameters.forEach(config::addDataSourceProperty);

    try {
      return new HikariDataSource(config);
    } finally {
      DriverManager.getDrivers().asIterator().forEachRemaining(driver -> {
        if (Settings.DATABASE.storageType.driverClassName.equals(driver.getClass().getName())) {
          try {
            DriverManager.deregisterDriver(driver);
          } catch (SQLException e) {
            //e.printStackTrace();
          }
        }
      });
    }
  }
}
