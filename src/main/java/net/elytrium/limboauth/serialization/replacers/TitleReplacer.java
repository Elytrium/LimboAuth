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

package net.elytrium.limboauth.serialization.replacers;

import java.util.regex.Pattern;
import net.elytrium.serializer.placeholders.PlaceholderReplacer;
import net.kyori.adventure.text.Component;
import net.kyori.adventure.title.Title;

public class TitleReplacer implements PlaceholderReplacer<Title, Pattern> {

  public static final TitleReplacer INSTANCE = new TitleReplacer();

  @Override
  public Title replace(Title value, Pattern[] placeholders, Object... values) {
    Component title = value.title();
    Component subtitle = value.subtitle();
    for (int index = Math.min(values.length, placeholders.length) - 1; index >= 0; --index) {
      title = ComponentReplacer.replace(title, placeholders[index], values[index]);
      subtitle = ComponentReplacer.replace(subtitle, placeholders[index], values[index]);
    }

    return Title.title(title.compact(), subtitle.compact(), value.times());
  }

  @Override
  public Pattern transformPlaceholder(String placeholder) {
    return Pattern.compile(placeholder, Pattern.LITERAL);
  }
}
