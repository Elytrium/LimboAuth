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
import net.elytrium.limboauth.BuildConfig;
import net.elytrium.limboauth.Settings;
import org.jooq.SQLDialect;

public enum DataSourceType {

  // TODO hardcoded limboauth -> Settings.DATABASE.database
  H2_V1(BuildConfig.H2V1, SQLDialect.H2) { // TODO restore migration from v1 to v2 // remove this enum and merge with H2

    @Override
    public String getJdbcUrl(Path directory) {
      return "jdbc:h2:" + directory + "/limboauth";
    }
  },
  H2(BuildConfig.H2, SQLDialect.H2) { // TODO migrate from non pass to pass

    @Override
    public String getJdbcUrl(Path directory) {
      return "jdbc:h2:" + directory + "/limboauth-v2";
    }
  },
  // H2_V2 with pass here
  SQLITE(BuildConfig.SQLITE, SQLDialect.SQLITE) {
    @Override
    public String getJdbcUrl(Path directory) {
      return "jdbc:sqlite:" + directory + "/limboauth.db";
    }
  },
  MYSQL(BuildConfig.MYSQL, SQLDialect.MYSQL) {
    @Override
    public String getJdbcUrl(Path directory) {
      return "jdbc:mysql://" + Settings.DATABASE.hostname + '/' + Settings.DATABASE.database;
    }
  },
  MARIADB(BuildConfig.MARIADB, SQLDialect.MARIADB) {
    @Override
    public String getJdbcUrl(Path directory) {
      return "jdbc:mariadb://" + Settings.DATABASE.hostname + '/' + Settings.DATABASE.database;
    }
  },
  POSTGRESQL(BuildConfig.POSTGRESQL, SQLDialect.POSTGRES) {
    @Override
    public String getJdbcUrl(Path directory) {
      return "jdbc:postgresql://" + Settings.DATABASE.hostname + '/' + Settings.DATABASE.database;
    }
  };

  final String[] libraries;
  final SQLDialect sqlDialect;

  DataSourceType(String[] libraries, SQLDialect sqlDialect) {
    this.libraries = libraries;
    this.sqlDialect = sqlDialect;
  }

  public abstract String getJdbcUrl(Path directory);
}
