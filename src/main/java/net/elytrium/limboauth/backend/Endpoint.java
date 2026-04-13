/*
 * Copyright (C) 2021 - 2025 Elytrium
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

package net.elytrium.limboauth.backend;

import com.google.common.io.ByteArrayDataInput;
import com.google.common.io.ByteArrayDataOutput;
import net.elytrium.limboauth.LimboAuth;
import net.elytrium.limboauth.Settings;

public abstract class Endpoint {

  protected final LimboAuth plugin;
  protected String type;
  protected String username;

  public Endpoint(LimboAuth plugin) {
    this.plugin = plugin;
  }

  public Endpoint(LimboAuth plugin, String type, String username) {
    this.plugin = plugin;
    this.type = type;
    this.username = username;
  }

  public void write(ByteArrayDataOutput output) {
    output.writeUTF(this.type);
    if (!this.type.equals("available_endpoints") && !Settings.IMP.MAIN.BACKEND_API.ENABLED_ENDPOINTS.contains(this.type)) {
      output.writeInt(-1);
      output.writeUTF(this.username);
      return;
    }

    output.writeInt(1);
    output.writeUTF(Settings.IMP.MAIN.BACKEND_API.TOKEN);
    output.writeUTF(this.username);
    this.writeContents(output);
  }

  public void read(ByteArrayDataInput input) {
    int version = input.readInt();
    if (version != 0) {
      throw new IllegalStateException("unsupported '" + this.type + "' endpoint version: " + version);
    }

    this.username = input.readUTF();
    this.readContents(input);
  }

  public abstract void writeContents(ByteArrayDataOutput output);

  public abstract void readContents(ByteArrayDataInput input);
}
