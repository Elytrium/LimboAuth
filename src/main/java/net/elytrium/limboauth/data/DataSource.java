package net.elytrium.limboauth.data;

import java.nio.file.Path;
import java.util.function.Function;
import net.elytrium.limboauth.BuildConfig;
import net.elytrium.limboauth.Settings;
import org.jooq.SQLDialect;

public enum DataSource {

  H2_V1(BuildConfig.H2V1_DEPENDENCIES, SQLDialect.H2, "org.h2.Driver", dir -> "jdbc:h2:" + dir + "/limboauth"), // TODO restore migration
  H2(BuildConfig.H2_DEPENDENCIES, SQLDialect.H2, "org.h2.Driver", dir -> "jdbc:h2:" + dir + "/limboauth-v2"),
  SQLITE(BuildConfig.SQLITE_DEPENDENCIES, SQLDialect.SQLITE, "org.sqlite.JDBC", dir -> "jdbc:sqlite:" + dir + "/limboauth.db"),
  MYSQL(BuildConfig.MYSQL_DEPENDENCIES, SQLDialect.MYSQL, "com.mysql.cj.jdbc.Driver", dir -> "jdbc:mysql://" + Settings.DATABASE.hostname + '/' + Settings.DATABASE.database),
  MARIADB(BuildConfig.MARIADB_DEPENDENCIES, SQLDialect.MARIADB, "org.mariadb.jdbc.Driver", dir -> "jdbc:mariadb://" + Settings.DATABASE.hostname + '/' + Settings.DATABASE.database),
  POSTGRESQL(BuildConfig.POSTGRESQL_DEPENDENCIES, SQLDialect.POSTGRES, "org.postgresql.Driver", dir -> "jdbc:postgresql://" + Settings.DATABASE.hostname + '/' + Settings.DATABASE.database);

  public final String[] libraries;
  public final SQLDialect sqlDialect;
  public final String driverClassName;
  public final Function<Path, String> jdbcUrl;

  DataSource(String[] libraries, SQLDialect sqlDialect, String driverClassName, Function<Path, String> jdbcUrl) {
    this.libraries = libraries;
    this.sqlDialect = sqlDialect;
    this.driverClassName = driverClassName;
    this.jdbcUrl = jdbcUrl;
  }
}
