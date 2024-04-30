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

package net.elytrium.limboauth.utils;

public class HexHashing {

  public static String md5(String input) {
    return Hex.encodeString(Hashing.md5(input));
  }

  public static String md5(byte[] input) {
    return Hex.encodeString(Hashing.md5(input));
  }

  public static String sha1(String input) {
    return Hex.encodeString(Hashing.sha1(input));
  }

  public static String sha1(byte[] input) {
    return Hex.encodeString(Hashing.sha1(input));
  }

  public static String sha256(String input) {
    return Hex.encodeString(Hashing.sha256(input));
  }

  public static String sha256(byte[] input) {
    return Hex.encodeString(Hashing.sha256(input));
  }

  public static String sha512(String input) {
    return Hex.encodeString(Hashing.sha512(input));
  }

  public static String sha512(byte[] input) {
    return Hex.encodeString(Hashing.sha512(input));
  }
}
