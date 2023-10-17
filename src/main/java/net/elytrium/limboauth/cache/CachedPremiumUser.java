package net.elytrium.limboauth.cache;

public class CachedPremiumUser extends CachedUser {

  private final boolean premium;

  public CachedPremiumUser(long checkTime, boolean premium) {
    super(checkTime);

    this.premium = premium;
  }

  public boolean isPremium() {
    return this.premium;
  }
}
