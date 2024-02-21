/*
 * Copyright (C) 2024 Elytrium
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

package net.elytrium.limboauth.utils;

import java.time.Duration;
import java.util.Map;
import net.elytrium.fastutil.ints.Int2ObjectLinkedOpenHashMap;
import net.elytrium.fastutil.objects.Object2DoubleLinkedOpenHashMap;
import net.elytrium.fastutil.objects.Object2IntLinkedOpenHashMap;
import net.elytrium.fastutil.objects.Object2ObjectLinkedOpenHashMap;
import net.kyori.adventure.util.Ticks;

@SuppressWarnings("unchecked")
public class Maps {

  public static <R, K> R getChecked(Map<? super K, ?> map, K key) {
    return (R) map.get(key);
  }

  public static <K> Duration getTicksDuration(Map<? super K, ?> map, K key, Duration defaultValue) {
    Number value = Maps.getNumber(map, key);
    if (value == null) {
      return defaultValue;
    }

    long ticks = value instanceof Long result ? result : value.longValue();
    return defaultValue.toMillis() / Ticks.SINGLE_TICK_DURATION_MS == ticks ? defaultValue : Ticks.duration(ticks);
  }

  public static <K> float getFloat(Map<? super K, ?> map, K key, float defaultValue) {
    Number value = Maps.getNumber(map, key);
    return value == null ? defaultValue : value instanceof Float result ? result : value.floatValue();
  }

  public static <K> int getInt(Map<? super K, ?> map, K key, int defaultValue) {
    Number value = Maps.getNumber(map, key);
    return value == null ? defaultValue : value instanceof Integer result ? result : value.intValue();
  }

  public static <K> Number getNumber(Map<? super K, ?> map, K key) {
    return Maps.getNumber(map, key, null);
  }

  public static <K> Number getNumber(Map<? super K, ?> map, K key, Number defaultValue) {
    if (map != null) {
      Object value = map.get(key);
      if (value instanceof Number result) {
        return result;
      } else if (value instanceof String result) {
        try {
          try {
            return Long.parseLong(result);
          } catch (NumberFormatException e) {
            return Double.parseDouble(result);
          }
        } catch (NumberFormatException e) {
          /*
          try {
            return NumberFormat.getInstance().parse(result);
          } catch (Throwable t) {
            // Увы
          }
          */
        }
      }
    }

    return defaultValue;
  }

  public static <K> boolean getBoolean(Map<? super K, ?> map, K key) {
    return Maps.getBoolean(map, key, false);
  }

  public static <K> boolean getBoolean(Map<? super K, ?> map, K key, boolean defaultValue) {
    if (map != null) {
      Object value = map.get(key);
      return value == null ? defaultValue : value instanceof Boolean result ? result : Boolean.parseBoolean(String.valueOf(value));
    }

    return defaultValue;
  }

  public static <K> String getString(Map<? super K, ?> map, K key) {
    return Maps.getString(map, key, null);
  }

  public static <K> String getString(Map<? super K, ?> map, K key, String defaultValue) {
    if (map != null) {
      Object value = map.get(key);
      return value == null ? defaultValue : value instanceof String result ? result : String.valueOf(value);
    }

    return defaultValue;
  }

  public static <V> Int2ObjectLinkedOpenHashMap<V> i2o(Object... values) {
    int capacity = values.length >> 1;
    if (capacity != values.length / 2.0) {
      throw new IllegalArgumentException("Invalid arguments amount was provided!");
    }

    Int2ObjectLinkedOpenHashMap<V> result = new Int2ObjectLinkedOpenHashMap<>(capacity);
    for (int i = 0; i < values.length; ++i) {
      result.put(((Number) values[i]).intValue(), (V) values[++i]);
    }

    return result;
  }

  public static <K, V> Object2ObjectLinkedOpenHashMap<K, V> o2o(Object... values) {
    int capacity = values.length >> 1;
    if (capacity != values.length / 2.0) {
      throw new IllegalArgumentException("Invalid arguments amount was provided!");
    }

    Object2ObjectLinkedOpenHashMap<K, V> result = new Object2ObjectLinkedOpenHashMap<>(capacity);
    for (int i = 0; i < values.length; ++i) {
      result.put((K) values[i], (V) values[++i]);
    }

    return result;
  }

  public static <K> Object2IntLinkedOpenHashMap<K> o2i(Object... values) {
    int capacity = values.length >> 1;
    if (capacity != values.length / 2.0) {
      throw new IllegalArgumentException("Invalid arguments amount was provided!");
    }

    Object2IntLinkedOpenHashMap<K> result = new Object2IntLinkedOpenHashMap<>(capacity);
    for (int i = 0; i < values.length; ++i) {
      result.put((K) values[i], ((Number) values[++i]).intValue());
    }

    return result;
  }

  public static <K> Object2DoubleLinkedOpenHashMap<K> o2d(Object... values) {
    int capacity = values.length >> 1;
    if (capacity != values.length / 2.0) {
      throw new IllegalArgumentException("Invalid arguments amount was provided!");
    }

    Object2DoubleLinkedOpenHashMap<K> result = new Object2DoubleLinkedOpenHashMap<>(capacity);
    for (int i = 0; i < values.length; ++i) {
      result.put((K) values[i], ((Number) values[++i]).doubleValue());
    }

    return result;
  }
}
