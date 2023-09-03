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
import java.util.Map;
import java.util.concurrent.ExecutorService;
import org.jooq.DSLContext;
import org.jooq.SQLDialect;
import org.jooq.impl.DSL;

public enum DatabaseLibrary {
  H2_LEGACY_V1(
      BaseLibrary.H2_V1,
      SQLDialect.H2,
      "org.h2.Driver",
          (dir, hostname, database) -> "jdbc:h2:" + dir + "/limboauth"
  ),
  H2(
      BaseLibrary.H2_V2,
      SQLDialect.H2,
      "org.h2.Driver",
          (dir, hostname, database) -> "jdbc:h2:" + dir + "/limboauth-v2"
  ),
  MYSQL(
      BaseLibrary.MYSQL,
      SQLDialect.MYSQL,
      "com.mysql.cj.jdbc.Driver",
          (dir, hostname, database) -> "jdbc:mysql://" + hostname + "/" + database
  ),
  MARIADB(
      BaseLibrary.MARIADB,
      SQLDialect.MARIADB,
      "org.mariadb.jdbc.Driver",
          (dir, hostname, database) -> "jdbc:mariadb://" + hostname + "/" + database
  ),
  POSTGRESQL(
      BaseLibrary.POSTGRESQL,
      SQLDialect.POSTGRES,
      "org.postgresql.Driver",
          (dir, hostname, database) -> "jdbc:postgresql://" + hostname + "/" + database
  ),
  SQLITE(
      BaseLibrary.SQLITE,
      SQLDialect.SQLITE,
      "org.sqlite.JDBC",
          (dir, hostname, database) -> "jdbc:sqlite:" + dir + "/limboauth.db"
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

  public DSLContext connect(Path dir, String hostname, String database, Map<String, String> connectionParameters, String user, String password, ExecutorService executor) {
    System.setProperty("org.jooq.no-logo", "true");
    System.setProperty("org.jooq.no-tips", "true");

    HikariConfig config = new HikariConfig();
    config.setPoolName("limboauth-db-pool");

    config.setDriverClassName(this.className);
    config.setJdbcUrl(this.stringGetter.getJdbcString(dir, hostname, database));
    config.setUsername(user);
    config.setPassword(password);

    // config.setConnectionTimeout(database.connectionTimeout);
    // config.setMaxLifetime(database.maxLifetime);
    // config.setMaximumPoolSize(database.maximumPoolSize);
    // config.setMinimumIdle(database.minimumIdle);
    // config.setKeepaliveTime(database.keepaliveTime);
    config.setInitializationFailTimeout(-1);

    config.addDataSourceProperty("cachePrepStmts", "true");
    config.addDataSourceProperty("prepStmtCacheSize", "250");
    config.addDataSourceProperty("prepStmtCacheSqlLimit", "2048");
    config.addDataSourceProperty("useServerPrepStmts", "true");
    config.addDataSourceProperty("useLocalSessionState", "true");
    config.addDataSourceProperty("rewriteBatchedStatements", "true");
    config.addDataSourceProperty("cacheResultSetMetadata", "true");
    config.addDataSourceProperty("cacheServerConfiguration", "true");
    config.addDataSourceProperty("elideSetAutoCommits", "true");
    config.addDataSourceProperty("maintainTimeStats", "false");
    config.addDataSourceProperty("alwaysSendSetIsolation", "false");
    config.addDataSourceProperty("cacheCallableStmts", "true");
    config.addDataSourceProperty("socketTimeout", "30000");
    connectionParameters.forEach(config::addDataSourceProperty);

    HikariDataSource dataSource = new HikariDataSource(config);
    Runtime.getRuntime().addShutdownHook(new Thread(dataSource::close));

    DSLContext dslContext = DSL.using(dataSource, this.sqlDialect);
    dslContext.configuration().set(() -> executor);
    return dslContext;
  }

  public interface DatabaseStringGetter {
    String getJdbcString(Path dir, String hostname, String database);
  }
}
