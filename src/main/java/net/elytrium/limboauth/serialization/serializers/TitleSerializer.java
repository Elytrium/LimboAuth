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
import java.util.Objects;
import net.elytrium.limboauth.Settings;
import net.elytrium.limboauth.utils.Maps;
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

    Title.Times times = Objects.requireNonNullElse(from.times(), Title.DEFAULT_TIMES);
    return Maps.o2o(
        "title", from.title(),
        "subtitle", from.subtitle(),
        "times", Maps.o2i(
            "fade-in", times.fadeIn().toMillis() / Ticks.SINGLE_TICK_DURATION_MS,
            "stay", times.stay().toMillis() / Ticks.SINGLE_TICK_DURATION_MS,
            "fade-out", times.fadeOut().toMillis() / Ticks.SINGLE_TICK_DURATION_MS
        )
    );
  }

  @Override
  public Title deserialize(Map<String, Object> from) {
    if (from == null || from.isEmpty()) {
      return null;
    }

    Map<String, Object> times = Maps.getChecked(from, "times");
    return Title.title(
        Settings.SERIALIZER.deserialize(Maps.getString(from, "title")),
        Settings.SERIALIZER.deserialize(Maps.getString(from, "subtitle")),
        times == null ? Title.DEFAULT_TIMES : Title.Times.times(
            Maps.getTicksDuration(times, "fade-in", Title.DEFAULT_TIMES.fadeIn()),
            Maps.getTicksDuration(times, "stay", Title.DEFAULT_TIMES.stay()),
            Maps.getTicksDuration(times, "fade-out", Title.DEFAULT_TIMES.fadeOut())
        )
    );
  }
}
