package net.elytrium.limboauth.auth;

import java.util.UUID;

public class PremiumResponse {

  public static final PremiumResponse CRACKED = new PremiumResponse(PremiumState.CRACKED);
  public static final PremiumResponse PREMIUM = new PremiumResponse(PremiumState.PREMIUM);
  public static final PremiumResponse UNKNOWN = new PremiumResponse(PremiumState.UNKNOWN);
  public static final PremiumResponse RATE_LIMIT = new PremiumResponse(PremiumState.RATE_LIMIT);
  public static final PremiumResponse ERROR = new PremiumResponse(PremiumState.ERROR);

  private final PremiumState state;
  private final UUID uuid;

  public PremiumResponse(PremiumState state) {
    this.state = state;
    this.uuid = null;
  }

  public PremiumResponse(PremiumState state, UUID uuid) {
    this.state = state;
    this.uuid = uuid;
  }

  public PremiumResponse(PremiumState state, String uuid) {
    this.state = state;
    this.uuid = uuid.contains("-") ? UUID.fromString(uuid) : new UUID(Long.parseUnsignedLong(uuid.substring(0, 16), 16), Long.parseUnsignedLong(uuid.substring(16), 16));
  }

  public PremiumState getState() {
    return this.state;
  }

  public UUID getUuid() {
    return this.uuid;
  }
}
