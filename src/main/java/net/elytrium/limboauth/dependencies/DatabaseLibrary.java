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

package net.elytrium.limboauth.dependencies;

import com.zaxxer.hikari.HikariConfig;
import com.zaxxer.hikari.HikariDataSource;
import java.nio.file.Path;
import java.util.concurrent.ExecutorService;
import net.elytrium.limboauth.LimboAuth;
import net.elytrium.limboauth.Settings;
import org.jooq.DSLContext;
import org.jooq.SQLDialect;
import org.jooq.impl.DSL;

public enum DatabaseLibrary {
  H2_LEGACY_V1(
      BaseLibrary.H2_V1,
      SQLDialect.H2,
      "org.h2.Driver",
          (dir, databaseSettings) -> "jdbc:h2:" + dir + "/limboauth"
  ),
  H2(
      BaseLibrary.H2_V2,
      SQLDialect.H2,
      "org.h2.Driver",
          (dir, databaseSettings) -> "jdbc:h2:" + dir + "/limboauth-v2"
  ),
  MYSQL(
      BaseLibrary.MYSQL,
      SQLDialect.MYSQL,
      "com.mysql.cj.jdbc.Driver",
          (dir, databaseSettings) -> "jdbc:mysql://" + databaseSettings.hostname + "/" + databaseSettings.database
  ),
  MARIADB(
      BaseLibrary.MARIADB,
      SQLDialect.MARIADB,
      "org.mariadb.jdbc.Driver",
          (dir, databaseSettings) -> "jdbc:mariadb://" + databaseSettings.hostname + "/" + databaseSettings.database
  ),
  POSTGRESQL(
      BaseLibrary.POSTGRESQL,
      SQLDialect.POSTGRES,
      "org.postgresql.Driver",
          (dir, databaseSettings) -> "jdbc:postgresql://" + databaseSettings.hostname + "/" + databaseSettings.database
  ),
  SQLITE(
      BaseLibrary.SQLITE,
      SQLDialect.SQLITE,
      "org.sqlite.JDBC",
          (dir, databaseSettings) -> "jdbc:sqlite:" + dir + "/limboauth.db"
  );

  private final BaseLibrary baseLibrary;
  private final SQLDialect sqlDialect;
  private final String className;
  private final DatabaseStringGetter stringGetter;

  DatabaseLibrary(BaseLibrary baseLibrary, SQLDialect sqlDialect, String className, DatabaseStringGetter stringGetter) {
    this.baseLibrary = baseLibrary;
    this.sqlDialect = sqlDialect;
    this.className = className;
    this.stringGetter = stringGetter;
  }

  public DSLContext connect(LimboAuth plugin, Path dir, Settings.Database databaseSettings, ExecutorService executor) {
    plugin.getServer().getPluginManager().addToClasspath(plugin, this.baseLibrary.getClassPath());

    System.setProperty("org.jooq.no-logo", "true");
    System.setProperty("org.jooq.no-tips", "true");

    HikariConfig config = new HikariConfig();
    config.setPoolName("limboauth-db-pool");

    config.setDriverClassName(this.className);
    config.setJdbcUrl(this.stringGetter.getJdbcString(dir, databaseSettings));
    config.setUsername(databaseSettings.user);
    config.setPassword(databaseSettings.password);
    config.setInitializationFailTimeout(-1);

    databaseSettings.connectionParameters.forEach(config::addDataSourceProperty);

    HikariDataSource dataSource = new HikariDataSource(config);
    Runtime.getRuntime().addShutdownHook(new Thread(dataSource::close));

    DSLContext dslContext = DSL.using(dataSource, this.sqlDialect);
    dslContext.configuration().set(() -> executor);
    return dslContext;
  }

  public interface DatabaseStringGetter {
    String getJdbcString(Path dir, Settings.Database databaseSettings);
  }
}
