/*
 * Copyright (C) 2021 - 2023 Elytrium
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

package net.elytrium.limboauth.events;

import com.velocitypowered.api.proxy.Player;
import java.util.function.Consumer;
import net.elytrium.limboauth.data.PlayerData;

public class PreAuthorizationEvent extends PreEvent {

  private final PlayerData playerInfo;

  public PreAuthorizationEvent(Consumer<TaskEvent> onComplete, Result result, Player player, PlayerData playerInfo) {
    super(onComplete, result, player);

    this.playerInfo = playerInfo;
  }

  public PlayerData getPlayerInfo() {
    return this.playerInfo;
  }
}
