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
import java.util.function.Function;
import net.elytrium.limboauth.LimboAuth;
import net.elytrium.limboauth.Settings;
import net.elytrium.limboauth.backend.Endpoint;

public class StringEndpoint extends Endpoint {

  private String type;
  private Function<String, String> function;
  private String username;
  private String value;

  public StringEndpoint(LimboAuth plugin, String type, Function<String, String> function) {
    super(plugin);
    this.type = type;
    this.function = function;
  }

  public StringEndpoint(LimboAuth plugin, String type, String username, String value) {
    super(plugin);
    this.type = type;
    this.username = username;
    this.value = value;
  }

  @Override
  public void write(ByteArrayDataOutput output) {
    output.writeUTF(this.type);
    if (!this.type.equals("available_endpoints") && !Settings.IMP.MAIN.BACKEND_API.ENABLED_ENDPOINTS.contains(this.type)) {
      output.writeInt(-1);
      output.writeUTF(this.username);
      return;
    }

    output.writeInt(0);
    output.writeUTF(this.username);
    output.writeUTF(this.value);
  }

  @Override
  public void read(ByteArrayDataInput input) {
    int version = input.readInt();
    if (version != 0) {
      throw new IllegalStateException("unsupported '" + this.type + "' endpoint version: " + version);
    }

    this.username = input.readUTF();
    this.value = this.function.apply(this.username);
  }

  @Override
  public String toString() {
    return "StringEndpoint{"
        + "username='" + this.username + '\''
        + ", value=" + this.value
        + '}';
  }
}
