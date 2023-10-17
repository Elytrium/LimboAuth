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

import net.elytrium.limboauth.Settings;
import net.elytrium.serializer.custom.ClassSerializer;
import net.kyori.adventure.text.Component;

public class ComponentSerializer extends ClassSerializer<Component, String> {

  public static final ComponentSerializer INSTANCE = new ComponentSerializer();

  @Override
  public String serialize(Component from) {
    return from == null ? "" : Settings.HEAD.serializer.getSerializer().serialize(from);
  }

  @Override
  public Component deserialize(String from) {
    return from.isEmpty() ? null : Settings.HEAD.serializer.getSerializer().deserialize(from);
  }
}
