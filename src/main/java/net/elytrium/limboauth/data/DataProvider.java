package net.elytrium.limboauth.data;

import net.elytrium.limboauth.dependencies.BaseLibrary;
import net.elytrium.limboauth.dependencies.hikary.HikariRegisteredPlayerRepository;
import net.elytrium.limboauth.repository.RegisteredPlayerRepository;

import java.nio.file.Path;

public enum DataProvider {
    MYSQL(BaseLibrary.MYSQL),
    MARIADB(BaseLibrary.MARIADB),
    POSTGRESQL(BaseLibrary.POSTGRESQL),
    H2_LEGACY(BaseLibrary.H2_V1),
    H2(BaseLibrary.H2_V2),
    SQLITE(BaseLibrary.SQLITE);

    private final BaseLibrary baseLibrary;

    DataProvider(BaseLibrary baseLibrary) {
        this.baseLibrary = baseLibrary;
    }

    public RegisteredPlayerRepository createRegisteredPlayerRepository(
            Path path,
            String host,
            String database,
            String user,
            String password
    ) throws Exception {
        HikariRegisteredPlayerRepository repo = new HikariRegisteredPlayerRepository(
                this,
                path,
                host,
                database,
                user,
                password
        );
        repo.updateSchema(this);
        return repo;
    }

    public BaseLibrary getBaseLibrary() {
        return baseLibrary;
    }
}
