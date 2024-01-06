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
import com.j256.ormlite.support.ConnectionSource;
import com.velocitypowered.api.proxy.Player;
import net.elytrium.limboauth.Settings;
import net.elytrium.limboauth.model.AuthenticateEntity;
import net.elytrium.limboauth.model.NotRegisteredPlayer;
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

    private final Map<String, AuthenticateEntity> cache = new ConcurrentHashMap<>();

    public PlayerStorage(ConnectionSource source) {
        DaoUtils.createTableIfNotExists(source, RegisteredPlayer.class);

        try {
            this.playerDao = DaoManager.createDao(source, RegisteredPlayer.class);
        } catch (SQLException e) {
            throw new SQLRuntimeException(e);
        }
    }

    public void trySave() {
        CompletableFuture.runAsync(() -> {

            System.out.println("Start save player storage.");

            DaoUtils.callBatchTasks(playerDao, () -> {

                cache.values().forEach(entity -> {
                    if(entity instanceof NotRegisteredPlayer){
                        cache.remove(((NotRegisteredPlayer) entity).getUsername().toLowerCase(Locale.ROOT));
                    }
                });

                cache.values().forEach(entity -> {
                    if(!(entity instanceof RegisteredPlayer)) return;

                    RegisteredPlayer player = (RegisteredPlayer) entity;


                    if(player.isNeedSave()) {
                        DaoUtils.updateSilent(playerDao, player, true);
                    }

                });

                return null;
            }, () -> {
                cache.values().forEach(entity -> {

                    if(!(entity instanceof RegisteredPlayer)) return;
                    RegisteredPlayer player = (RegisteredPlayer) entity;
                    player.setNeedSave(false);

                });

                System.out.println("Player storage success saved.");
                return null;
            });
        }).exceptionally(throwable -> {
            System.out.println("Player storage had some issues with saving data.");
            throwable.printStackTrace();

            return null;
        }).orTimeout(10, TimeUnit.SECONDS);
    }

    public void migrate() {
        DaoUtils.migrateDb(playerDao);
    }

    public CompletableFuture<Long> getAccountCount(InetAddress ip) {
        String keyIp = ip.getHostAddress();

        return CompletableFuture.supplyAsync(() -> DaoUtils.count(playerDao,
                DaoUtils.getWhereQuery(playerDao, RegisteredPlayer.IP_FIELD, keyIp)));
    }

    public RegisteredPlayer getAccount(UUID id) {
        String key = id.toString();

        AuthenticateEntity entity = cache.values().stream()
                .filter(e -> e instanceof RegisteredPlayer &&
                        Objects.equals(((RegisteredPlayer) e).getPremiumUuid(), key))
                .findAny().orElse(null);

        if (entity == null) {
            entity = DaoUtils.queryForFieldSilent(playerDao, RegisteredPlayer.PREMIUM_UUID_FIELD, key);

            if (entity != null) {
                cache.put(((RegisteredPlayer) entity).getLowercaseNickname(), entity);
            }
        }

        return entity instanceof RegisteredPlayer ? (RegisteredPlayer) entity : null;
    }

    public RegisteredPlayer getAccount(String username) {
        username = usernameKey(username);

        AuthenticateEntity entity = cache.get(username);

        if (entity == null) {
            entity = DaoUtils.queryForIdSilent(playerDao, username);

            // NotRegisteredPlayer от флуда ботами и чтобы не кешить всех в бд
            cache.put(username, entity != null ? entity : new NotRegisteredPlayer(username, Instant.now()));
        }

        return entity instanceof RegisteredPlayer ? (RegisteredPlayer) entity : null;
    }

    public ChangePasswordResult changePassword(String username, String newPassword) {
        RegisteredPlayer entity = getAccount(username);

        if (entity == null) {
            return ChangePasswordResult.NOT_REGISTERED;
        }

        entity.setHash(RegisteredPlayer.genHash(newPassword));
        entity.setTokenIssuedAt(0L);

        return ChangePasswordResult.SUCCESS;
    }

    public boolean changeSession(String username, String ip) {
        RegisteredPlayer entity = getAccount(username);

        if (entity == null) {
            return false;
        }

        entity.setIP(ip);
        entity.setTokenIssuedAt(Instant.now().toEpochMilli());

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

        cache.put(usernameKey(entity.getNickname()), entity);
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

            entity = new RegisteredPlayer(username.replace('\u0451', '\u0435'), uuid, ip)
                    .setHash(RegisteredPlayer.genHash(password));

            entity.setIP(ip);
            entity.setLoginIp(ip);
            entity.setTokenIssuedAt(Instant.now().toEpochMilli());

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

        entity.setIP(ip);
        entity.setLoginIp(ip);
        entity.setTokenIssuedAt(Instant.now().toEpochMilli());

        return LoginRegisterResult.LOGGED_IN;
    }

    public boolean logout(String username) {
        RegisteredPlayer entity = getAccount(username);

        if (entity == null) {
            return false;
        }

        if (entity.getTokenIssuedAt() <= 0) {
            return false;
        }

        entity.setTokenIssuedAt(null);
        DaoUtils.updateSilent(playerDao, entity, false);
        cache.remove(usernameKey(username));

        return true;
    }

    public ResumeSessionResult resumeSession(String ip, String username) {
        RegisteredPlayer entity = getAccount(username);

        if (entity == null || !entity.getIP().equals(ip)) {
            return ResumeSessionResult.NOT_LOGGED_IN;
        }

        if (!usernameKey(username).equals(entity.getLowercaseNickname())) {
            return new ResumeSessionResult.InvalidUsernameCase(entity.getNickname());
        }

        long now = Instant.now().toEpochMilli();

        if (entity.getTokenIssuedAt() <= 0) {
            return ResumeSessionResult.NOT_LOGGED_IN;
        }

        long sessionDuration = Settings.IMP.MAIN.PURGE_CACHE_MILLIS;

        if(sessionDuration > 0 && now >= (entity.getTokenIssuedAt() + sessionDuration)) {
            return ResumeSessionResult.NOT_LOGGED_IN;
        }

        entity.setTokenIssuedAt(now);

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
        return input.toLowerCase().replace('\u0451', '\u0435');
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
