package net.elytrium.limboauth.repository;

import net.elytrium.limboauth.model.RegisteredPlayer;
import net.elytrium.limboauth.repository.exception.DataAccessException;

import java.io.Closeable;
import java.util.List;
import java.util.Optional;

public interface RegisteredPlayerRepository extends Closeable {

    void deleteByLowercaseName(String name) throws DataAccessException;

    Optional<RegisteredPlayer> getByLowercaseName(String name) throws DataAccessException;

    List<RegisteredPlayer> getByIp(String ip) throws DataAccessException;

    List<RegisteredPlayer> getByPremiumUUID(String uuid) throws DataAccessException;

    void createIfNotExists(RegisteredPlayer player) throws DataAccessException;

    void update(RegisteredPlayer player) throws DataAccessException;

    void updateHash(String lowercaseName, String hash) throws DataAccessException;

    void updateTotpToken(String lowercaseName, String token) throws DataAccessException;

    void updateLogin(String lowercase, String loginIp, Long loginDate) throws DataAccessException;

    boolean isHashEmptyByPremiumUuid(String uuid) throws DataAccessException;

    boolean isHashEmptyByLowercaseName(String name) throws DataAccessException;

    int registeredPlayerCount();

}
