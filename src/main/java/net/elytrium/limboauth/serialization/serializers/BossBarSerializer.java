/*
 * Copyright (C) 2023-2024 Elytrium
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

package net.elytrium.limboauth.serialization.serializers;

import java.util.Map;
import net.elytrium.limboauth.Settings;
import net.elytrium.limboauth.utils.Maps;
import net.elytrium.serializer.custom.ClassSerializer;
import net.kyori.adventure.bossbar.BossBar;

@SuppressWarnings("DataFlowIssue")
public class BossBarSerializer extends ClassSerializer<BossBar, Map<String, String>> {

  public static final BossBarSerializer INSTANCE = new BossBarSerializer();

  @Override
  public Map<String, String> serialize(BossBar from) { // TODO progress, flags
    return Maps.o2o(
        "name", from.name(),
        "color", BossBar.Color.NAMES.key(from.color()),
        "overlay", BossBar.Overlay.NAMES.key(from.overlay())
    );
  }

  @Override
  public BossBar deserialize(Map<String, String> from) {
    return BossBar.bossBar(
        Settings.SERIALIZER.deserialize(from.get("name")),
        1.0F,
        BossBar.Color.NAMES.value(from.get("color")),
        BossBar.Overlay.NAMES.value(from.get("overlay"))
    );
  }
}
