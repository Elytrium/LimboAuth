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

package net.elytrium.limboauth.events;

import java.util.function.Consumer;
import net.elytrium.limboapi.api.player.LimboPlayer;
import net.elytrium.limboauth.data.PlayerData;

public abstract class PostEvent extends TaskEvent {

  private final LimboPlayer player;
  private final PlayerData playerInfo;
  private final String password;

  protected PostEvent(Consumer<TaskEvent> onComplete, LimboPlayer player, PlayerData playerInfo, String password) {
    super(onComplete);

    this.player = player;
    this.playerInfo = playerInfo;
    this.password = password;
  }

  protected PostEvent(Consumer<TaskEvent> onComplete, Result result, LimboPlayer player, PlayerData playerInfo, String password) {
    super(onComplete, result);

    this.player = player;
    this.playerInfo = playerInfo;
    this.password = password;
  }

  public LimboPlayer getPlayer() {
    return this.player;
  }

  public PlayerData getPlayerInfo() {
    return this.playerInfo;
  }

  public String getPassword() {
    return this.password;
  }
}
