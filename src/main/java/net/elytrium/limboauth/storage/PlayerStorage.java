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

package net.elytrium.limboauth.storage;

import com.j256.ormlite.dao.Dao;
import com.j256.ormlite.dao.DaoManager;
import com.j256.ormlite.stmt.PreparedQuery;
import com.j256.ormlite.stmt.QueryBuilder;
import com.j256.ormlite.support.ConnectionSource;
import com.velocitypowered.api.proxy.Player;
import java.net.InetAddress;
import java.sql.SQLException;
import java.util.Map;
import java.util.UUID;
import java.util.concurrent.CompletableFuture;
import java.util.concurrent.ConcurrentHashMap;
import java.util.concurrent.TimeUnit;
import net.elytrium.limboauth.LimboAuth;
import net.elytrium.limboauth.Settings;
import net.elytrium.limboauth.model.RegisteredPlayer;
import net.elytrium.limboauth.model.SQLRuntimeException;
import net.elytrium.limboauth.util.CryptUtils;
import net.elytrium.limboauth.util.DaoUtils;

public class PlayerStorage {
  private final Dao<RegisteredPlayer, String> playerDao;

  private final Map<String, RegisteredPlayer> cache = new ConcurrentHashMap<>();

  public PlayerStorage(ConnectionSource source) {
    DaoUtils.createTableIfNotExists(source, RegisteredPlayer.class);

    try {
      this.playerDao = DaoManager.createDao(source, RegisteredPlayer.class);
    } catch (SQLException e) {
      throw new SQLRuntimeException(e);
    }
  }

  public void trySave() {
    CompletableFuture.runAsync(() -> DaoUtils.callBatchTasks(this.playerDao, () -> {
      this.cache.values().forEach(registeredPlayer -> {
        if (registeredPlayer.isNeedSave()) {
          DaoUtils.updateSilent(this.playerDao, registeredPlayer, true);
        }
      });

      return null;
    }, () -> {
      this.cache.values().forEach(registeredPlayer -> registeredPlayer.setNeedSave(false));
      this.cache.entrySet().removeIf(p -> LimboAuth.getProxy().getAllPlayers().stream()
          .noneMatch(player -> usernameKey(player.getUsername()).equals(p.getKey())));
      return null;
    })).exceptionally(throwable -> {
      throwable.printStackTrace();
      return null;
    }).orTimeout(10, TimeUnit.SECONDS);
  }

  public void save() {
    this.cache.values().forEach(registeredPlayer -> {
      if (registeredPlayer.isNeedSave()) {
        DaoUtils.updateSilent(this.playerDao, registeredPlayer, true);
      }
    });
  }

  public void migrate() {
    DaoUtils.migrateDb(this.playerDao);
  }

  public CompletableFuture<Long> getAccountCount(InetAddress ip) {
    String keyIp = ip.getHostAddress();

    try {
      QueryBuilder<RegisteredPlayer, String> queryBuilder = this.playerDao.queryBuilder();

      queryBuilder.setCountOf(true);

      long now = System.currentTimeMillis();
      queryBuilder.setWhere(queryBuilder.where().eq(RegisteredPlayer.IP_FIELD, keyIp).and()
          .between(RegisteredPlayer.REG_DATE_FIELD, now - Settings.IMP.MAIN.IP_LIMIT_VALID_TIME, now));

      PreparedQuery<RegisteredPlayer> query = queryBuilder.prepare();

      return CompletableFuture.supplyAsync(() -> DaoUtils.count(this.playerDao, query));
    } catch (SQLException e) {
      e.printStackTrace();
      return CompletableFuture.completedFuture(0L);
    }
  }

  public RegisteredPlayer getAccount(UUID id) {
    String key = id.toString();

    RegisteredPlayer entity = this.cache.values().stream()
        .filter(e -> e.getPremiumUuid().equals(key))
        .findAny().orElse(null);

    if (entity == null) {
      entity = DaoUtils.queryForFieldSilent(this.playerDao, RegisteredPlayer.PREMIUM_UUID_FIELD, key);
      if (entity != null) {
        this.cache.put(entity.getLowercaseNickname(), entity);
      }
    }

    return entity;
  }

  public RegisteredPlayer getAccount(String username) {
    String usernameKey = usernameKey(username);

    RegisteredPlayer entity = this.cache.get(usernameKey);

    if (entity == null) {
      entity = DaoUtils.queryForIdSilent(this.playerDao, usernameKey);
      if (entity != null) {
        this.cache.put(usernameKey, entity);
      }
    }

    return entity;
  }

  public ChangePasswordResult changePassword(String username, String newPassword) {
    RegisteredPlayer entity = this.getAccount(username);

    if (entity == null) {
      return ChangePasswordResult.NOT_REGISTERED;
    }

    entity.setHash(RegisteredPlayer.genHash(newPassword));
    entity.setLoginDate(0L);

    return ChangePasswordResult.SUCCESS;
  }

  public boolean isRegistered(String username) {
    return this.getAccount(username) != null;
  }

  public RegisteredPlayer registerPremium(Player player) {
    RegisteredPlayer entity = this.getAccount(player.getUniqueId());

    if (entity != null) {
      return entity;
    }

    entity = new RegisteredPlayer(player)
        .setPremiumUuid(player.getUniqueId());

    this.cache.put(usernameKey(player.getUsername()), entity);
    DaoUtils.createSilent(this.playerDao, entity);

    return entity;
  }

  public LoginRegisterResult loginOrRegister(String username, String uuid, String ip, String password) {
    RegisteredPlayer entity = this.getAccount(username);

    if (entity == null) {

      if (Settings.IMP.MAIN.MIN_PASSWORD_LENGTH > password.length()) {
        return LoginRegisterResult.TOO_SHORT_PASSWORD;
      } else if (Settings.IMP.MAIN.MAX_PASSWORD_LENGTH < password.length()) {
        return LoginRegisterResult.TOO_LONG_PASSWORD;
      }

      entity = new RegisteredPlayer(username, uuid, ip)
          .setHash(RegisteredPlayer.genHash(password));

      entity.setIP(ip);
      entity.setRegDate(System.currentTimeMillis());

      this.cache.put(usernameKey(username), entity);
      DaoUtils.createSilent(this.playerDao, entity);

      return LoginRegisterResult.REGISTERED;
    }

    if (!username.equals(entity.getNickname())) {
      return new LoginRegisterResult.InvalidUsernameCase(entity.getNickname());
    }

    if (!CryptUtils.checkPassword(password, entity)) {
      return LoginRegisterResult.INVALID_PASSWORD;
    }

    return LoginRegisterResult.LOGGED_IN;
  }

  public ResumeSessionResult resumeSession(String ip, String username) {
    RegisteredPlayer entity = this.getAccount(username);

    if (entity == null || !entity.getLoginIp().equals(ip)) {
      return ResumeSessionResult.NOT_LOGGED_IN;
    }

    if (!username.equals(entity.getNickname())) {
      return new ResumeSessionResult.InvalidUsernameCase(entity.getNickname());
    }

    long now = System.currentTimeMillis();

    if (entity.getLoginDate() <= 0) {
      return ResumeSessionResult.NOT_LOGGED_IN;
    }

    long sessionDuration = Settings.IMP.MAIN.PURGE_CACHE_MILLIS;

    if (sessionDuration > 0 && now >= (entity.getLoginDate() + sessionDuration)) {
      return ResumeSessionResult.NOT_LOGGED_IN;
    }

    entity.setLoginDate(now);

    return ResumeSessionResult.RESUMED;
  }

  public boolean unregister(String username) {
    RegisteredPlayer entity = this.getAccount(username);

    if (entity == null) {
      return false;
    }

    if (DaoUtils.deleteSilent(this.playerDao, entity) <= 0) {
      return false;
    }

    this.cache.remove(usernameKey(username));

    return true;
  }


  private static String usernameKey(final String input) {
    return input.toLowerCase();
  }

  public enum ChangePasswordResult {
    NOT_REGISTERED,
    UNDER_COOLDOWN,
    SUCCESS
  }

  public Dao<RegisteredPlayer, String> getPlayerDao() {
    return this.playerDao;
  }

  public static class LoginRegisterResult {
    public static final LoginRegisterResult INVALID_PASSWORD;
    public static final LoginRegisterResult TOO_SHORT_PASSWORD;

    public static final LoginRegisterResult TOO_LONG_PASSWORD;
    public static final LoginRegisterResult LOGGED_IN;
    public static final LoginRegisterResult REGISTERED;

    private LoginRegisterResult() {
    }

    static {
      INVALID_PASSWORD = new LoginRegisterResult();
      TOO_SHORT_PASSWORD = new LoginRegisterResult();
      TOO_LONG_PASSWORD = new LoginRegisterResult();
      LOGGED_IN = new LoginRegisterResult();
      REGISTERED = new LoginRegisterResult();
    }

    public static final class InvalidUsernameCase extends LoginRegisterResult {
      public final String username;

      public InvalidUsernameCase(final String username) {
        this.username = username;
      }
    }
  }

  public static class ResumeSessionResult {
    public static final ResumeSessionResult NOT_LOGGED_IN;
    public static final ResumeSessionResult RESUMED;

    private ResumeSessionResult() {
    }

    static {
      NOT_LOGGED_IN = new ResumeSessionResult();
      RESUMED = new ResumeSessionResult();
    }

    public static final class InvalidUsernameCase extends ResumeSessionResult {
      public final String username;

      public InvalidUsernameCase(final String username) {
        this.username = username;
      }
    }
  }

}
