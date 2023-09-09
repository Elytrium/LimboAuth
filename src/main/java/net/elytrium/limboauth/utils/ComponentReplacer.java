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

import java.util.regex.Pattern;
import net.elytrium.serializer.placeholders.PlaceholderReplacer;
import net.kyori.adventure.text.Component;

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
    return value.replaceText(builder -> builder.match(placeholder).replacement((result, input) -> replacement instanceof Component component ? component : input.content(String.valueOf(replacement))));
  }

  @Override
  public Pattern transformPlaceholder(String placeholder) {
    return Pattern.compile(placeholder, Pattern.LITERAL);
  }
}
