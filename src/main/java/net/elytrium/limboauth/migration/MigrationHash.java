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

import de.mkammerer.argon2.Argon2;
import de.mkammerer.argon2.Argon2Factory;
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
  AUTHME_NP((hash, password) -> {
    String[] arr = hash.split("\\$"); // SHA$salt$hash
    return arr.length == 3
        && arr[2].equals(MigrationHash.getDigest(MigrationHash.getDigest(password, "SHA-256") + arr[1], "SHA-256"));
  }),
  ARGON2(new Argon2Verifier()),
  SHA512_DBA((hash, password) -> {
    String[] arr = hash.split("\\$"); // SHA$salt$hash
    return arr.length == 3 && arr[2].equals(MigrationHash.getDigest(MigrationHash.getDigest(password, "SHA-512") + arr[1], "SHA-512"));
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
  }),
  MOON_SHA256((hash, password) -> {
    String[] arr = hash.split("\\$"); // $SHA$hash
    return arr.length == 3 && arr[2].equals(MigrationHash.getDigest(MigrationHash.getDigest(password, "SHA-256"), "SHA-256"));
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

  private static class Argon2Verifier implements MigrationHashVerifier {
    private Argon2 argon2;

    @Override
    public boolean checkPassword(String hash, String password) {
      if (this.argon2 == null) {
        this.argon2 = Argon2Factory.create();
      }

      return this.argon2.verify(hash, password.getBytes(StandardCharsets.UTF_8));
    }
  }
}
