package net.elytrium.limboauth.data;

import java.nio.file.Path;
import java.util.function.Function;
import net.elytrium.limboauth.BuildConfig;
import net.elytrium.limboauth.Settings;
import org.jooq.SQLDialect;

public enum DataSourceType {

  H2_V1(BuildConfig.H2V1, SQLDialect.H2, "org.h2.Driver", directory -> "jdbc:h2:" + directory + "/limboauth"), // TODO restore migration
  H2(BuildConfig.H2, SQLDialect.H2, "org.h2.Driver", directory -> "jdbc:h2:" + directory + "/limboauth-v2"),
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
