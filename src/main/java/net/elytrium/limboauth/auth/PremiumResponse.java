/*
 * Copyright (C) 2021-2023 Elytrium
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
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

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
