/*
 * Copyright (C) 2021 Elytrium
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

package net.elytrium.limboauth.migration;

import java.math.BigInteger;
import java.nio.charset.StandardCharsets;
import java.security.MessageDigest;
import java.security.NoSuchAlgorithmException;

@SuppressWarnings("unused")
public enum MigrationHash {

  AUTHME((hash, password) -> {
    String[] arr = hash.split("\\$"); // $SHA$salt$hash
    return arr.length == 4
        && arr[3].equals(MigrationHash.getDigest(MigrationHash.getDigest(password, "SHA-256") + arr[2], "SHA-256"));
  }),
  SHA512_NP((hash, password) -> {
    String[] arr = hash.split("\\$"); // SHA$salt$hash
    return arr.length == 3 && arr[2].equals(MigrationHash.getDigest(password + arr[1], "SHA-512"));
  }),
  SHA512_P((hash, password) -> {
    String[] arr = hash.split("\\$"); // $SHA$salt$hash
    return arr.length == 4 && arr[3].equals(MigrationHash.getDigest(password + arr[2], "SHA-512"));
  }),
  SHA256_NP((hash, password) -> {
    String[] arr = hash.split("\\$"); // SHA$salt$hash
    return arr.length == 3 && arr[2].equals(MigrationHash.getDigest(password + arr[1], "SHA-256"));
  }),
  SHA256_P((hash, password) -> {
    String[] arr = hash.split("\\$"); // $SHA$salt$hash
    return arr.length == 4 && arr[3].equals(MigrationHash.getDigest(password + arr[2], "SHA-256"));
  }),
  MD5((hash, password) -> {
    return hash.equals(MigrationHash.getDigest(password, "MD5"));
  });

  final MigrationHashVerifier verifier;

  MigrationHash(MigrationHashVerifier verifier) {
    this.verifier = verifier;
  }

  public boolean checkPassword(String hash, String password) {
    return this.verifier.checkPassword(hash, password);
  }

  private static String getDigest(String string, String algo) {
    try {
      MessageDigest messageDigest = MessageDigest.getInstance(algo);
      messageDigest.reset();
      messageDigest.update(string.getBytes(StandardCharsets.UTF_8));
      byte[] array = messageDigest.digest();
      return String.format("%0" + (array.length << 1) + "x", new BigInteger(1, array));
    } catch (NoSuchAlgorithmException e) {
      throw new IllegalArgumentException(e);
    }
  }
}
