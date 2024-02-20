/*
 * Copyright (C) 2021 - 2024 Elytrium
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

package net.elytrium.limboauth.util;

import com.j256.ormlite.dao.Dao;
import com.j256.ormlite.dao.GenericRawResults;
import com.j256.ormlite.db.DatabaseType;
import com.j256.ormlite.field.FieldType;
import com.j256.ormlite.stmt.PreparedQuery;
import com.j256.ormlite.stmt.QueryBuilder;
import com.j256.ormlite.support.ConnectionSource;
import com.j256.ormlite.table.TableInfo;
import com.j256.ormlite.table.TableUtils;
import java.sql.SQLException;
import java.util.Collections;
import java.util.HashSet;
import java.util.List;
import java.util.Set;
import java.util.concurrent.Callable;
import java.util.function.Supplier;
import net.elytrium.limboauth.LimboAuth;
import net.elytrium.limboauth.Settings;
import net.elytrium.limboauth.dependencies.DatabaseLibrary;
import net.elytrium.limboauth.model.SQLRuntimeException;

public class DaoUtils {

  public static <T> void createTableIfNotExists(ConnectionSource source, Class<T> clazz) {
    try {
      TableUtils.createTableIfNotExists(source, clazz);
    } catch (Exception e) {
      if (!e.getMessage().contains("CREATE INDEX")) {
        LimboAuth.getLogger().error(e.getMessage());
      }
    }
  }

  public static <T, S> void callBatchTasks(Dao<T, S> dao, Callable<Void> callable, Callable<Void> success) {
    try {
      dao.callBatchTasks(callable);
      success.call();
    } catch (Exception exception) {
      exception.printStackTrace();
    }
  }

  public static <T, S> long count(Dao<T, S> dao, PreparedQuery<T> preparedQuery) {
    try {
      return dao.countOf(preparedQuery);
    } catch (Exception exception) {
      exception.printStackTrace();
      return 0;
    }
  }

  public static <T, S> PreparedQuery<T> getWhereQuery(Dao<T, S> dao, String field, Object value) {
    try {
      QueryBuilder<T, S> builder = dao.queryBuilder();

      builder.setCountOf(true);
      builder.setWhere(builder.where().eq(field, value));

      return builder.prepare();
    } catch (Exception exception) {
      exception.printStackTrace();
      return null;
    }
  }

  public static <T, S> T queryForFieldSilent(Dao<T, S> dao, String field, Object value) {
    try {
      List<T> result = dao.queryForEq(field, value);

      return result.isEmpty() ? null : result.get(0);
    } catch (Exception exception) {
      exception.printStackTrace();
      return null;
    }
  }

  public static <T, S> T queryForIdSilent(Dao<T, S> dao, S id) {
    try {
      return dao.queryForId(id);
    } catch (Exception exception) {
      exception.printStackTrace();
      return null;
    }
  }

  public static <T, S> T createSilent(Dao<T, S> dao, T entity) {
    try {
      return dao.createIfNotExists(entity);
    } catch (Exception exception) {
      exception.printStackTrace();
      return null;
    }
  }

  public static <T, S> int updateSilent(Dao<T, S> dao, T entity, boolean rethrow) {
    try {
      return dao.update(entity);
    } catch (Exception exception) {
      if (rethrow) {
        throw new SQLRuntimeException(exception);
      }

      exception.printStackTrace();
      return 0;
    }
  }

  public static <T, S> int deleteSilent(Dao<T, S> dao, T entity) {
    try {
      return dao.delete(entity);
    } catch (Exception exception) {
      exception.printStackTrace();
      return 0;
    }
  }

  public static <T> T callQuerySafe(Supplier<T> query) {
    try {
      return query.get();
    } catch (Exception exception) {
      LimboAuth.getLogger().error(exception.getMessage());

      return null;
    }
  }

  public static void migrateDb(Dao<?, ?> dao) {
    TableInfo<?, ?> tableInfo = dao.getTableInfo();

    Set<FieldType> tables = new HashSet<>();
    Collections.addAll(tables, tableInfo.getFieldTypes());

    String findSql;
    String database = Settings.IMP.DATABASE.DATABASE;
    String tableName = tableInfo.getTableName();
    DatabaseLibrary databaseLibrary = Settings.IMP.DATABASE.STORAGE_TYPE;

    switch (databaseLibrary) {
      case SQLITE: {
        findSql = "SELECT name FROM PRAGMA_TABLE_INFO('" + tableName + "')";
        break;
      }
      case H2: {
        findSql = "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = '" + tableName + "';";
        break;
      }
      case POSTGRESQL: {
        findSql = "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_CATALOG = '" + database + "' AND TABLE_NAME = '" + tableName + "';";
        break;
      }
      case MARIADB:
      case MYSQL: {
        findSql = "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = '" + database + "' AND TABLE_NAME = '" + tableName + "';";
        break;
      }
      default: {
        throw new IllegalArgumentException("WRONG DATABASE TYPE.");
      }
    }

    try (GenericRawResults<String[]> queryResult = dao.queryRaw(findSql)) {
      queryResult.forEach(result -> tables.removeIf(table -> table.getColumnName().equalsIgnoreCase(result[0])));

      tables.forEach(table -> {
        try {
          StringBuilder builder = new StringBuilder("ALTER TABLE ");
          if (databaseLibrary == DatabaseLibrary.POSTGRESQL) {
            builder.append('"');
          }
          builder.append(tableName);
          if (databaseLibrary == DatabaseLibrary.POSTGRESQL) {
            builder.append('"');
          }
          builder.append(" ADD ");
          String columnDefinition = table.getColumnDefinition();
          DatabaseType databaseType = dao.getConnectionSource().getDatabaseType();
          if (columnDefinition == null) {
            List<String> dummy = List.of();
            databaseType.appendColumnArg(table.getTableName(), builder, table, dummy, dummy, dummy, dummy);
          } else {
            databaseType.appendEscapedEntityName(builder, table.getColumnName());
            builder.append(" ").append(columnDefinition).append(" ");
          }

          dao.executeRawNoArgs(builder.toString());
        } catch (SQLException e) {
          throw new SQLRuntimeException(e);
        }
      });
    } catch (Exception e) {
      throw new SQLRuntimeException(e);
    }
  }

}
