package net.elytrium.limboauth.model;

public class UnsafePlayer {

    private final String username;
    private final String password;

    public UnsafePlayer(String username, String password) {
        this.username = username;
        this.password = password;
    }

    public String getUsername() {
        return username;
    }

    public String getPassword() {
        return password;
    }

}
