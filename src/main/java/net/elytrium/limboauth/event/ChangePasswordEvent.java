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

package net.elytrium.limboauth.event;

import net.elytrium.limboauth.model.RegisteredPlayer;
import org.checkerframework.checker.nullness.qual.Nullable;

public class ChangePasswordEvent {

  private final RegisteredPlayer playerInfo;
  @Nullable
  private final String oldPassword;
  private final String oldHash;
  private final String newPassword;
  private final String newHash;

  public ChangePasswordEvent(RegisteredPlayer playerInfo, @Nullable String oldPassword,
                             String oldHash, String newPassword, String newHash) {
    this.playerInfo = playerInfo;
    this.oldPassword = oldPassword;
    this.oldHash = oldHash;
    this.newPassword = newPassword;
    this.newHash = newHash;
  }

  public RegisteredPlayer getPlayerInfo() {
    return this.playerInfo;
  }

  @Nullable
  public String getOldPassword() {
    return this.oldPassword;
  }

  public String getOldHash() {
    return this.oldHash;
  }

  public String getNewPassword() {
    return this.newPassword;
  }

  public String getNewHash() {
    return this.newHash;
  }
}
