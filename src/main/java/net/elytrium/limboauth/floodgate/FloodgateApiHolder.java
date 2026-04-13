/*
 * Copyright (C) 2021 - 2025 Elytrium
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
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

package net.elytrium.limboauth.floodgate;

import java.util.UUID;
import org.geysermc.floodgate.api.FloodgateApi;

/**
 * Holder class for optional floodgate feature, we can't inject of optional plugins without holders due to Velocity structure.
 */
public class FloodgateApiHolder {

  private final FloodgateApi floodgateApi;

  public FloodgateApiHolder() {
    this.floodgateApi = FloodgateApi.getInstance();
  }

  public boolean isFloodgatePlayer(UUID uuid) {
    return this.floodgateApi.isFloodgatePlayer(uuid);
  }

  public int getPrefixLength() {
    return this.floodgateApi.getPlayerPrefix().length();
  }
}
