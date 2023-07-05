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

package net.elytrium.limboauth.migration;

import de.mkammerer.argon2.Argon2;
import de.mkammerer.argon2.Argon2Factory;
import java.nio.charset.StandardCharsets;
import java.security.MessageDigest;
import java.security.NoSuchAlgorithmException;
import org.apache.commons.codec.binary.Hex;
import org.checkerframework.checker.nullness.qual.MonotonicNonNull;

@SuppressWarnings("unused")
public enum MigrationHash {

  AUTHME((hash, password) -> {
    String[] args = hash.split("\\$"); // $SHA$salt$hash
    return args.length == 4 && args[3].equals(getDigest(getDigest(password, "SHA-256") + args[2], "SHA-256"));
  }),
  AUTHME_NP((hash, password) -> {
    String[] args = hash.split("\\$"); // SHA$salt$hash
    return args.length == 3 && args[2].equals(getDigest(getDigest(password, "SHA-256") + args[1], "SHA-256"));
  }),
  ARGON2(new Argon2Verifier()),
  SHA512_DBA((hash, password) -> {
    String[] args = hash.split("\\$"); // SHA$salt$hash
    return args.length == 3 && args[2].equals(getDigest(getDigest(password, "SHA-512") + args[1], "SHA-512"));
  }),
  SHA512_NP((hash, password) -> {
    String[] args = hash.split("\\$"); // SHA$salt$hash
    return args.length == 3 && args[2].equals(getDigest(password + args[1], "SHA-512"));
  }),
  SHA512_P((hash, password) -> {
    String[] args = hash.split("\\$"); // $SHA$salt$hash
    return args.length == 4 && args[3].equals(getDigest(password + args[2], "SHA-512"));
  }),
  SHA256_NP((hash, password) -> {
    String[] args = hash.split("\\$"); // SHA$salt$hash
    return args.length == 3 && args[2].equals(getDigest(password + args[1], "SHA-256"));
  }),
  SHA256_P((hash, password) -> {
    String[] args = hash.split("\\$"); // $SHA$salt$hash
    return args.length == 4 && args[3].equals(getDigest(password + args[2], "SHA-256"));
  }),
  MD5((hash, password) -> hash.equals(getDigest(password, "MD5"))),
  MOON_SHA256((hash, password) -> {
    String[] args = hash.split("\\$"); // $SHA$hash
    return args.length == 3 && args[2].equals(getDigest(getDigest(password, "SHA-256"), "SHA-256"));
  }),
  SHA256_NO_SALT((hash, password) -> {
    String[] args = hash.split("\\$"); // $SHA$hash
    return args.length == 3 && args[2].equals(getDigest(password, "SHA-256"));
  }),
  SHA512_NO_SALT((hash, password) -> {
    String[] args = hash.split("\\$"); // $SHA$hash
    return args.length == 3 && args[2].equals(getDigest(password, "SHA-512"));
  }),
  SHA512_P_REVERSED_HASH((hash, password) -> {
    String[] args = hash.split("\\$"); // $SHA$hash$salt
    return args.length == 4 && args[2].equals(getDigest(password + args[3], "SHA-512"));
  }),
  SHA512_NLOGIN((hash, password) -> {
    String[] args = hash.split("\\$"); // $SHA$hash$salt
    return args.length == 4 && args[2].equals(getDigest(getDigest(password, "SHA-512") + args[3], "SHA-512"));
  }),
  PLAINTEXT(String::equals);

  private final MigrationHashVerifier verifier;

  MigrationHash(MigrationHashVerifier verifier) {
    this.verifier = verifier;
  }

  public boolean checkPassword(String hash, String password) {
    return this.verifier.checkPassword(hash, password);
  }

  private static String getDigest(String string, String algorithm) {
    try {
      MessageDigest messageDigest = MessageDigest.getInstance(algorithm);
      messageDigest.update(string.getBytes(StandardCharsets.UTF_8));
      byte[] array = messageDigest.digest();
      return Hex.encodeHexString(array);
    } catch (NoSuchAlgorithmException e) {
      throw new IllegalArgumentException(e);
    }
  }

  private static class Argon2Verifier implements MigrationHashVerifier {

    @MonotonicNonNull
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
