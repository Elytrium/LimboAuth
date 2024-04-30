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

import it.unimi.dsi.fastutil.objects.Object2ObjectLinkedOpenHashMap;
import it.unimi.dsi.fastutil.objects.ObjectSet;
import java.util.List;
import java.util.Map;
import java.util.Set;
import java.util.function.Supplier;
import net.elytrium.limboauth.utils.Maps;
import net.elytrium.serializer.custom.ClassSerializer;
import net.kyori.adventure.bossbar.BossBar;
import net.kyori.adventure.text.Component;
import net.kyori.adventure.text.serializer.ComponentSerializer;

public class BossBarSerializer extends ClassSerializer<BossBar, Map<String, Object>> {

  private final Supplier<ComponentSerializer<Component, Component, String>> serializer;

  public BossBarSerializer(Supplier<ComponentSerializer<Component, Component, String>> serializer) {
    this.serializer = serializer;
  }

  @Override
  public Map<String, Object> serialize(BossBar from) {
    Object2ObjectLinkedOpenHashMap<String, Object> result = new Object2ObjectLinkedOpenHashMap<>(5);
    result.put("name", from.name());
    float progress = from.progress();
    if (progress != BossBar.MAX_PROGRESS) {
      result.put("progress", progress);
    }
    result.put("color", BossBar.Color.NAMES.key(from.color()));
    result.put("overlay", BossBar.Overlay.NAMES.key(from.overlay()));
    Set<BossBar.Flag> flags = from.flags();
    if (!flags.isEmpty()) {
      result.put("flags", flags.stream().map(BossBar.Flag.NAMES::key).toList());
    }

    return result;
  }

  @Override
  @SuppressWarnings("DataFlowIssue")
  public BossBar deserialize(Map<String, Object> from) {
    List<String> flags = Maps.getChecked(from, "flags");
    return BossBar.bossBar(
        this.serializer.get().deserialize(Maps.getString(from, "name")),
        Maps.getFloat(from, "progress", BossBar.MAX_PROGRESS),
        BossBar.Color.NAMES.value(Maps.getString(from, "color")),
        BossBar.Overlay.NAMES.value(Maps.getString(from, "overlay")),
        flags == null || flags.isEmpty() ? Set.of() : ObjectSet.of(flags.stream().map(BossBar.Flag.NAMES::value).toArray(BossBar.Flag[]::new))
    );
  }
}
