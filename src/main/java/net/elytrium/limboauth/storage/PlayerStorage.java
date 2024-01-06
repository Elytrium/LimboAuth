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

package net.elytrium.limboauth.storage;

import com.j256.ormlite.dao.Dao;
import com.j256.ormlite.dao.DaoManager;
import com.j256.ormlite.stmt.PreparedQuery;
import com.j256.ormlite.stmt.QueryBuilder;
import com.j256.ormlite.support.ConnectionSource;
import com.velocitypowered.api.proxy.Player;
import net.elytrium.limboauth.Settings;
import net.elytrium.limboauth.model.RegisteredPlayer;
import net.elytrium.limboauth.model.SQLRuntimeException;
import net.elytrium.limboauth.util.CryptUtils;
import net.elytrium.limboauth.util.DaoUtils;

import java.net.InetAddress;
import java.sql.SQLException;
import java.time.Instant;
import java.util.Locale;
import java.util.Map;
import java.util.Objects;
import java.util.UUID;
import java.util.concurrent.CompletableFuture;
import java.util.concurrent.ConcurrentHashMap;
import java.util.concurrent.TimeUnit;

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
        CompletableFuture.runAsync(() -> DaoUtils.callBatchTasks(playerDao, () -> {
            cache.values().forEach(registeredPlayer -> {
                if(registeredPlayer.isNeedSave()) {
                    DaoUtils.updateSilent(playerDao, registeredPlayer, true);
                }
            });

            return null;
        }, () -> {
            cache.values().forEach(registeredPlayer -> registeredPlayer.setNeedSave(false));
            return null;
        })).exceptionally(throwable -> {
            throwable.printStackTrace();
            return null;
        }).orTimeout(10, TimeUnit.SECONDS);
    }

    public void migrate() {
        DaoUtils.migrateDb(playerDao);
    }

    public CompletableFuture<Long> getAccountCount(InetAddress ip) {
        String keyIp = ip.getHostAddress();

        try {
            QueryBuilder<RegisteredPlayer, String> queryBuilder = playerDao.queryBuilder();

            queryBuilder.setCountOf(true);

            long now = System.currentTimeMillis();
            queryBuilder.setWhere(queryBuilder.where().eq(RegisteredPlayer.IP_FIELD, keyIp).and().between(RegisteredPlayer.REG_DATE_FIELD, now - Settings.IMP.MAIN.IP_LIMIT_VALID_TIME, now));

            PreparedQuery<RegisteredPlayer> query = queryBuilder.prepare();

            return CompletableFuture.supplyAsync(() -> DaoUtils.count(playerDao, query));
        } catch (SQLException e) {
            e.printStackTrace();
            return CompletableFuture.completedFuture(0L);
        }
    }

    public RegisteredPlayer getAccount(UUID id) {
        String key = id.toString();

        RegisteredPlayer entity = cache.values().stream()
                .filter(e -> e.getPremiumUuid().equals(key))
                .findAny().orElse(null);

        if (entity == null) {
            entity = DaoUtils.queryForFieldSilent(playerDao, RegisteredPlayer.PREMIUM_UUID_FIELD, key);
        }

        return entity;
    }

    public RegisteredPlayer getAccount(String username) {
        username = usernameKey(username);

        RegisteredPlayer entity = cache.get(username);

        if (entity == null) {
            entity = DaoUtils.queryForIdSilent(playerDao, username);
        }

        return entity;
    }

    public ChangePasswordResult changePassword(String username, String newPassword) {
        RegisteredPlayer entity = getAccount(username);

        if (entity == null) {
            return ChangePasswordResult.NOT_REGISTERED;
        }

        entity.setHash(RegisteredPlayer.genHash(newPassword));
        entity.setLoginDate(0L);

        return ChangePasswordResult.SUCCESS;
    }

    public boolean changeSession(String username, String ip) {
        RegisteredPlayer entity = getAccount(username);

        if (entity == null) {
            return false;
        }

        entity.setLoginIp(ip);
        entity.setLoginDate(Instant.now().toEpochMilli());

        return true;
    }

    public boolean isRegistered(String username) {
        return getAccount(username) != null;
    }

    public RegisteredPlayer registerPremium(Player player) {
        RegisteredPlayer entity = getAccount(player.getUniqueId());

        if (entity != null) return entity;

        entity = new RegisteredPlayer(player)
                .setPremiumUuid(player.getUniqueId());

        cache.put(usernameKey(player.getUsername()), entity);
        DaoUtils.createSilent(playerDao, entity);

        return entity;
    }

    public LoginRegisterResult loginOrRegister(String username, String uuid, String ip, String password) {
        RegisteredPlayer entity = getAccount(username);

        if (entity == null) {

            if (Settings.IMP.MAIN.MIN_PASSWORD_LENGTH > password.length()) {
                return LoginRegisterResult.TOO_SHORT_PASSWORD;
            } else if (Settings.IMP.MAIN.MAX_PASSWORD_LENGTH < password.length()) {
                return LoginRegisterResult.TOO_LONG_PASSWORD;
            }

            entity = new RegisteredPlayer(username, uuid, ip)
                    .setHash(RegisteredPlayer.genHash(password));

            entity.setIP(ip);
            entity.setRegDate(Instant.now().toEpochMilli());

            cache.put(usernameKey(username), entity);
            DaoUtils.createSilent(playerDao, entity);

            return LoginRegisterResult.REGISTERED;
        }

        if (!usernameKey(username).equals(entity.getLowercaseNickname())) {
            return new LoginRegisterResult.InvalidUsernameCase(entity.getNickname());
        }

        if (!CryptUtils.checkPassword(password, entity)) {
            return LoginRegisterResult.INVALID_PASSWORD;
        }

        return LoginRegisterResult.LOGGED_IN;
    }

    public boolean logout(String username) {
        RegisteredPlayer entity = getAccount(username);

        if (entity == null) {
            return false;
        }

        if (entity.getLoginDate() <= 0) {
            return false;
        }

        entity.setLoginDate(null);
        DaoUtils.updateSilent(playerDao, entity, false);
        cache.remove(usernameKey(username));

        return true;
    }

    public ResumeSessionResult resumeSession(String ip, String username) {
        RegisteredPlayer entity = getAccount(username);

        if (entity == null || !entity.getLoginIp().equals(ip)) {
            return ResumeSessionResult.NOT_LOGGED_IN;
        }

        if (!username.equals(entity.getNickname())) {
            return new ResumeSessionResult.InvalidUsernameCase(entity.getNickname());
        }

        long now = Instant.now().toEpochMilli();

        if (entity.getLoginDate() <= 0) {
            return ResumeSessionResult.NOT_LOGGED_IN;
        }

        long sessionDuration = Settings.IMP.MAIN.PURGE_CACHE_MILLIS;

        if(sessionDuration > 0 && now >= (entity.getLoginDate() + sessionDuration)) {
            return ResumeSessionResult.NOT_LOGGED_IN;
        }

        return ResumeSessionResult.RESUMED;
    }

    public boolean unregister(String username) {
        RegisteredPlayer entity = getAccount(username);

        if (entity == null) {
            return false;
        }

        if (DaoUtils.deleteSilent(playerDao, entity) <= 0) return false;
        cache.remove(usernameKey(username));

        return true;
    }


    private static String usernameKey(final String input) {
        return input.toLowerCase(Locale.ROOT).replace('\u0451', '\u0435');
    }

    public enum ChangePasswordResult {
        NOT_REGISTERED,
        UNDER_COOLDOWN,
        SUCCESS
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
