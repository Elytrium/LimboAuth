package net.elytrium.limboauth.utils;

import java.time.Duration;
import java.util.Map;
import net.elytrium.fastutil.objects.Object2ObjectLinkedOpenHashMap;
import net.kyori.adventure.util.Ticks;

@SuppressWarnings("unchecked")
public class Maps {

  public static <R, K, V> R getChecked(Map<? super K, ? super V> map, K key) {
    return (R) map.get(key);
  }

  public static <K> Duration getTicksDuration(Map<? super K, ?> map, K key, Duration defaultValue) {
    Number value = Maps.getNumber(map, key);
    if (value == null) {
      return defaultValue;
    }

    long ticks = value instanceof Long result ? result : value.longValue();
    return defaultValue.getSeconds() == ticks * Ticks.TICKS_PER_SECOND ? defaultValue : Ticks.duration(ticks);
  }

  public static <K> float getFloat(Map<? super K, ?> map, K key, float defaultValue) {
    Number value = Maps.getNumber(map, key);
    return value == null ? defaultValue : value instanceof Float result ? result : value.floatValue();
  }

  public static <K> Number getNumber(Map<? super K, ?> map, K key) {
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

    return null;
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
}
