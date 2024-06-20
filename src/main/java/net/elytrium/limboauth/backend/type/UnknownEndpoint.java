/*
 * Copyright (C) 2021 - 2024 Elytrium
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

package net.elytrium.limboauth.backend.type;

import com.google.common.io.ByteArrayDataInput;
import com.google.common.io.ByteArrayDataOutput;
import net.elytrium.limboauth.LimboAuth;
import net.elytrium.limboauth.backend.Endpoint;

public class UnknownEndpoint extends Endpoint {

  private String type;

  public UnknownEndpoint(LimboAuth plugin) {
    super(plugin);
  }

  public UnknownEndpoint(LimboAuth plugin, String type) {
    super(plugin);
    this.type = type;
  }

  @Override
  public void write(ByteArrayDataOutput output) {
    output.writeUTF(this.type);
    output.writeInt(-2);
  }

  @Override
  public void read(ByteArrayDataInput input) {
    throw new UnsupportedOperationException();
  }

  @Override
  public String toString() {
    return "UnknownEndpoint{"
        + "type='" + this.type + '\''
        + '}';
  }
}
