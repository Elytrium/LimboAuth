package net.elytrium.limboauth.cache;

import java.net.InetAddress;

public class CachedSessionUser extends CachedUser {

  private final InetAddress inetAddress;
  private final String username;

  public CachedSessionUser(long checkTime, InetAddress inetAddress, String username) {
    super(checkTime);

    this.inetAddress = inetAddress;
    this.username = username;
  }

  public InetAddress getInetAddress() {
    return this.inetAddress;
  }

  public String getUsername() {
    return this.username;
  }
}
