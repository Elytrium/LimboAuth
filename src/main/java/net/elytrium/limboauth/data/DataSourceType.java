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

import java.nio.file.Path;
import java.util.function.Function;
import net.elytrium.limboauth.BuildConfig;
import net.elytrium.limboauth.Settings;
import org.jooq.SQLDialect;

public enum DataSourceType {

  // TODO hardcoded limboauth -> Settings.DATABASE.database
  H2_V1(BuildConfig.H2V1, SQLDialect.H2, "org.h2.Driver", directory -> "jdbc:h2:" + directory + "/limboauth"), // TODO restore migration from v1 to v2 // remove this enum and merge with H2
  H2(BuildConfig.H2, SQLDialect.H2, "org.h2.Driver", directory -> "jdbc:h2:" + directory + "/limboauth-v2"), // TODO migrate from non pass to pass
  // H2_V2
  SQLITE(BuildConfig.SQLITE, SQLDialect.SQLITE, "org.sqlite.JDBC", directory -> "jdbc:sqlite:" + directory + "/limboauth.db"),
  MYSQL(BuildConfig.MYSQL, SQLDialect.MYSQL, "com.mysql.cj.jdbc.Driver", directory -> "jdbc:mysql://" + Settings.DATABASE.hostname + '/' + Settings.DATABASE.database),
  MARIADB(BuildConfig.MARIADB, SQLDialect.MARIADB, "org.mariadb.jdbc.Driver", directory -> "jdbc:mariadb://" + Settings.DATABASE.hostname + '/' + Settings.DATABASE.database),
  POSTGRESQL(BuildConfig.POSTGRESQL, SQLDialect.POSTGRES, "org.postgresql.Driver", directory -> "jdbc:postgresql://" + Settings.DATABASE.hostname + '/' + Settings.DATABASE.database);

  public final String[] libraries;
  public final SQLDialect sqlDialect;
  public final String driverClassName;
  public final Function<Path, String> jdbcUrl;

  DataSourceType(String[] libraries, SQLDialect sqlDialect, String driverClassName, Function<Path, String> jdbcUrl) {
    this.libraries = libraries;
    this.sqlDialect = sqlDialect;
    this.driverClassName = driverClassName;
    this.jdbcUrl = jdbcUrl;
  }
}
