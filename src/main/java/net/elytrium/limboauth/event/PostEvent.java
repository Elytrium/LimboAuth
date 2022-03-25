/*
 * Copyright (C) 2021 Elytrium
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

import net.elytrium.limboapi.api.player.LimboPlayer;
import net.elytrium.limboauth.model.RegisteredPlayer;

public abstract class PostEvent extends TaskEvent {

  private final LimboPlayer player;
  private final RegisteredPlayer playerInfo;

  protected PostEvent(LimboPlayer player, RegisteredPlayer playerInfo) {
    super();

    this.player = player;
    this.playerInfo = playerInfo;
  }

  public RegisteredPlayer getPlayerInfo() {
    return this.playerInfo;
  }

  public LimboPlayer getPlayer() {
    return this.player;
  }
}
