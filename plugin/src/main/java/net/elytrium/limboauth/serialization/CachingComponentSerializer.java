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

package net.elytrium.limboauth.serialization;

import it.unimi.dsi.fastutil.objects.Object2ObjectMap;
import it.unimi.dsi.fastutil.objects.Object2ObjectOpenHashMap;
import net.kyori.adventure.text.Component;
import net.kyori.adventure.text.minimessage.MiniMessage;
import net.kyori.adventure.text.serializer.ComponentSerializer;
import net.kyori.adventure.text.serializer.gson.GsonComponentSerializer;
import net.kyori.adventure.text.serializer.legacy.LegacyComponentSerializer;
import net.kyori.adventure.text.serializer.plain.PlainTextComponentSerializer;

@SuppressWarnings("NullableProblems") // No @NotNull 🥺
public enum CachingComponentSerializer implements ComponentSerializer<Component, Component, String> { // TODO SimpleComponentSerializer, DelegatingComponentSerializer, PassingComponentSerializer

  LEGACY_AMPERSAND(LegacyComponentSerializer.builder().character(LegacyComponentSerializer.AMPERSAND_CHAR).extractUrls().hexColors().build()),
  LEGACY_SECTION(LegacyComponentSerializer.builder().extractUrls().hexColors().build()),
  MINIMESSAGE(MiniMessage.miniMessage()) {
    @Override
    protected Component compact(Component component) {
      return component;
    }
  },
  GSON(GsonComponentSerializer.gson()) {
    @Override
    public Component deserializeOr(String input, Component fallback) {
      if (input == null) {
        return fallback;
      }

      Component cache = this.s2cCache.get(input);
      if (cache == null) {
        Component component = ((GsonComponentSerializer) this.delegate).serializer().fromJson(input, Component.class);
        return component == null ? fallback : this.compactAndCache(component, input);
      }

      return cache;
    }
  },
  PLAIN(PlainTextComponentSerializer.plainText());

  private static final boolean COMPACT;

  protected final Object2ObjectMap<String, Component> s2cCache = new Object2ObjectOpenHashMap<>(2);
  protected final Object2ObjectMap<Component, String> c2sCache = new Object2ObjectOpenHashMap<>(2);
  protected final ComponentSerializer<Component, Component, String> delegate;

  @SuppressWarnings("unchecked")
  CachingComponentSerializer(ComponentSerializer<Component, ? extends Component, String> delegate) {
    this.delegate = (ComponentSerializer<Component, Component, String>) delegate;
  }

  @Override
  public Component deserialize(String input) {
    Component cache = this.s2cCache.get(input);
    return cache == null ? this.compactAndCache(this.delegate.deserialize(input), input) : cache;
  }

  @Override
  public Component deserializeOr(String input, Component fallback) {
    return ComponentSerializer.super.deserializeOr(input, fallback);
  }

  @Override
  public String serialize(Component component) {
    String cache = this.c2sCache.get(component = this.compact(component));
    if (cache == null) {
      String result = this.delegate.serialize(component);
      this.cache(component, result);
      return result;
    }

    return cache;
  }

  protected Component compactAndCache(Component component, String input) {
    this.cache(component = this.compact(component), input);
    return component;
  }

  private void cache(Component component, String input) {
    this.s2cCache.put(input, component);
    this.c2sCache.put(component, input);
  }

  protected Component compact(Component component) {
    return CachingComponentSerializer.COMPACT ? component.compact() : component;
  }

  public void clearCache() {
    this.s2cCache.clear();
    this.c2sCache.clear();
  }

  static {
    boolean result;
    try {
      // compact() was introduced only in 4.9.0
      Component.class.getMethod("compact");
      result = true;
    } catch (NoSuchMethodException e) {
      result = false;
    }

    COMPACT = result;
  }
}
