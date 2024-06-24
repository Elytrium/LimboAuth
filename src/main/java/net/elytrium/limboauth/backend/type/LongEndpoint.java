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

public class LongEndpoint extends Endpoint {

  private Function<String, Long> function;
  private long value;

  public LongEndpoint(LimboAuth plugin, String type, Function<String, Long> function) {
    super(plugin, type, null);
    this.function = function;
  }

  public LongEndpoint(LimboAuth plugin, String type, String username, long value) {
    super(plugin, type, username);
    this.value = value;
  }

  @Override
  public void writeContents(ByteArrayDataOutput output) {
    output.writeLong(this.value);
  }

  @Override
  public void readContents(ByteArrayDataInput input) {
    this.value = this.function.apply(this.username);
  }

  @Override
  public String toString() {
    return "LongEndpoint{"
        + "username='" + this.username + '\''
        + ", value=" + this.value
        + '}';
  }
}
