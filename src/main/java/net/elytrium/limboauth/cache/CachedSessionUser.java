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
