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

package net.elytrium.limboauth.utils;

import it.unimi.dsi.fastutil.objects.Object2ObjectLinkedOpenHashMap;
import java.util.Map;
import java.util.Objects;
import net.elytrium.limboauth.Settings;
import net.elytrium.serializer.custom.ClassSerializer;
import net.kyori.adventure.title.Title;
import net.kyori.adventure.util.Ticks;

public class TitleSerializer extends ClassSerializer<Title, Map<String, Object>> {

  public static final TitleSerializer INSTANCE = new TitleSerializer();

  @Override
  public Map<String, Object> serialize(Title from) {
    if (from == null) {
      return null;
    }

    Map<String, Object> value = new Object2ObjectLinkedOpenHashMap<>(3);
    value.put("title", from.title());
    value.put("subtitle", from.subtitle());
    Title.Times times = Objects.requireNonNullElse(from.times(), Title.DEFAULT_TIMES);
    Map<String, Object> timesValue = new Object2ObjectLinkedOpenHashMap<>(3);
    timesValue.put("fade-in", times.fadeIn().toMillis() / Ticks.SINGLE_TICK_DURATION_MS);
    timesValue.put("stay", times.stay().toMillis() / Ticks.SINGLE_TICK_DURATION_MS);
    timesValue.put("fade-out", times.fadeOut().toMillis() / Ticks.SINGLE_TICK_DURATION_MS);
    value.put("times", timesValue);
    return value;
  }

  @Override
  @SuppressWarnings("unchecked")
  public Title deserialize(Map<String, Object> from) {
    if (from == null || from.isEmpty()) {
      return null;
    }

    var times = (Map<String, Object>) from.get("times");
    return Title.title(
        Settings.IMP.serializer.getSerializer().deserialize((String) from.get("title")),
        Settings.IMP.serializer.getSerializer().deserialize((String) from.get("subtitle")),
        times == null
            ? Title.DEFAULT_TIMES
            : Title.Times.times(
                Ticks.duration(((Number) times.get("fade-in")).longValue()),
                Ticks.duration(((Number) times.get("stay")).longValue()),
                Ticks.duration(((Number) times.get("fade-out")).longValue())
            )
    );
  }
}
