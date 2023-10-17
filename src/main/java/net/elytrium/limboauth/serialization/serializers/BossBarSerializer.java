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

package net.elytrium.limboauth.serialization.serializers;

import it.unimi.dsi.fastutil.objects.Object2ObjectLinkedOpenHashMap;
import java.util.Map;
import java.util.Objects;
import net.elytrium.limboauth.Settings;
import net.elytrium.serializer.custom.ClassSerializer;
import net.kyori.adventure.bossbar.BossBar;

public class BossBarSerializer extends ClassSerializer<BossBar, Map<String, Object>> {

  public static final BossBarSerializer INSTANCE = new BossBarSerializer();

  @Override
  public Map<String, Object> serialize(BossBar from) { // TODO progress, flags
    Map<String, Object> value = new Object2ObjectLinkedOpenHashMap<>(3);
    value.put("name", from.name());
    value.put("color", BossBar.Color.NAMES.key(from.color()));
    value.put("overlay", BossBar.Overlay.NAMES.key(from.overlay()));
    return value;
  }

  @Override
  public BossBar deserialize(Map<String, Object> from) {
    return BossBar.bossBar(
        Settings.HEAD.serializer.getSerializer().deserialize((String) from.get("name")),
        1.0F,
        Objects.requireNonNull(BossBar.Color.NAMES.value((String) from.get("color")), "bossbar.color"),
        Objects.requireNonNull(BossBar.Overlay.NAMES.value((String) from.get("overlay")), "bossbar.overlay")
    );
  }
}
