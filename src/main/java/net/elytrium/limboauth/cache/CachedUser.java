package net.elytrium.limboauth.cache;

public class CachedUser {

  private final long checkTime;

  public CachedUser(long checkTime) {
    this.checkTime = checkTime;
  }

  public long getCheckTime() {
    return this.checkTime;
  }
}