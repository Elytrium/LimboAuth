package net.elytrium.limboauth.cache;

public class CachedBruteforceUser extends LimboAuth.CachedUser {

  private int attempts;

  public CachedBruteforceUser(long checkTime) {
    super(checkTime);
  }

  public void incrementAttempts() {
    this.attempts++;
  }

  public int getAttempts() {
    return this.attempts;
  }
}
