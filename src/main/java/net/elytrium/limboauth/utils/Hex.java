/*
 * Copyright (C) 2021-2023 Elytrium
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

package net.elytrium.limboauth.utils;

public class Hex {

  private static final byte[] BYTE_TABLE = {
      '0', '1', '2', '3', '4', '5', '6', '7', '8', '9', 'a', 'b', 'c', 'd', 'e', 'f'
  };
  private static final char[] CHAR_TABLE = {
      '0', '1', '2', '3', '4', '5', '6', '7', '8', '9', 'a', 'b', 'c', 'd', 'e', 'f'
  };

  public static byte[] encodeBytes(byte[] data) {
    byte[] result = new byte[data.length << 1];
    for (int from = 0, to = 0; from < data.length; ++from) {
      result[to++] = Hex.BYTE_TABLE[(data[from] & 0xF0) >>> 4];
      result[to++] = Hex.BYTE_TABLE[data[from] & 0x0F];
    }

    return result;
  }

  public static char[] encodeChars(byte[] data) {
    char[] result = new char[data.length << 1];
    for (int from = 0, to = 0; from < data.length; ++from) {
      result[to++] = Hex.CHAR_TABLE[(data[from] & 0xF0) >>> 4];
      result[to++] = Hex.CHAR_TABLE[data[from] & 0x0F];
    }

    return result;
  }

  public static String encodeString(byte[] data) {
    return new String(Hex.encodeChars(data));
  }
}
