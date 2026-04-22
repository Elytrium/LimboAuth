package net.elytrium.limboauth.repository.exception;

public class DataAccessException extends Exception {

    public DataAccessException(Throwable cause) {
        this("Error was caught during the data access operation.", cause);
    }

    public DataAccessException(String message, Throwable cause) {
        super(message, cause);
    }
}
