/*
 * Copyright (C) 2021-2024 Elytrium
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

package net.elytrium.limboauth.serialization.replacers;

import it.unimi.dsi.fastutil.objects.ObjectArrayList;
import java.util.List;
import java.util.regex.Pattern;
import net.elytrium.serializer.placeholders.PlaceholderReplacer;
import net.kyori.adventure.text.Component;
import net.kyori.adventure.text.TextReplacementConfig;
import net.kyori.adventure.text.event.ClickEvent;
import net.kyori.adventure.text.format.Style;

public class ComponentReplacer implements PlaceholderReplacer<Component, Pattern> {

  public static final ComponentReplacer INSTANCE = new ComponentReplacer();

  @Override
  public Component replace(Component value, Pattern[] placeholders, Object... values) {
    for (int index = Math.min(values.length, placeholders.length) - 1; index >= 0; --index) {
      value = ComponentReplacer.replace(value, placeholders[index], values[index]);
    }

    return value.compact();
  }

  static Component replace(Component value, Pattern placeholder, Object replacement) {
    boolean component = replacement instanceof Component;
    String replacementString = component ? null : String.valueOf(replacement);

    if (!component) {
      Component replaced = ComponentReplacer.replaceClickEvent(value, placeholder, replacementString);
      if (replaced != null) {
        value = replaced;
      }
    }

    return value.replaceText(TextReplacementConfig.builder()
        .match(placeholder)
        .replacement((result, input) -> component ? (Component) replacement : input.content(replacementString))
        .build()
    );
  }

  private static Component replaceClickEvent(Component component, Pattern placeholder, String replacement) {
    if (component == Component.empty()) {
      return null;
    }

    Style style = component.style();
    replace:
    {
      ClickEvent clickEvent = style.clickEvent();
      if (clickEvent != null) {
        String clickEventValue = clickEvent.value();
        String replaced = placeholder.matcher(clickEventValue).replaceAll(replacement);
        if (!replaced.equals(clickEventValue)) {
          style = style.clickEvent(ClickEvent.clickEvent(clickEvent.action(), replaced));
          break replace;
        }
      }

      style = null;
    }

    List<Component> children = component.children();
    List<Component> processedChildren = null;
    if (!children.isEmpty()) {
      for (int i = children.size() - 1; i >= 0; --i) {
        Component replaced = ComponentReplacer.replaceClickEvent(children.get(i), placeholder, replacement);
        if (replaced != null) {
          if (processedChildren == null) {
            processedChildren = new ObjectArrayList<>(children);
          }

          processedChildren.set(i, replaced);
        }
      }
    }

    Component replaced = null;
    if (style != null) {
      replaced = component.style(style);
    }

    if (processedChildren != null) {
      replaced = (replaced == null ? component : replaced).children(processedChildren);
    }

    return replaced;
  }

  @Override
  public Pattern transformPlaceholder(String placeholder) {
    return Pattern.compile(placeholder, Pattern.LITERAL);
  }
}
