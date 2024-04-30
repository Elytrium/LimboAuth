/*
 * Copyright (C) 2023-2024 Elytrium
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

import java.nio.charset.StandardCharsets;
import java.security.InvalidKeyException;
import java.security.MessageDigest;
import java.security.NoSuchAlgorithmException;
import javax.crypto.Mac;
import javax.crypto.spec.SecretKeySpec;

public class Hashing {

  private static ThreadLocal<MessageDigest> MD5;
  private static ThreadLocal<MessageDigest> SHA1;
  private static ThreadLocal<MessageDigest> SHA256;
  private static ThreadLocal<MessageDigest> SHA512;

  private static ThreadLocal<Mac> HMAC_SHA1;

  public static byte[] md5(String input) {
    return Hashing.md5(input.getBytes(StandardCharsets.UTF_8));
  }

  public static byte[] md5(byte[] input) {
    if (Hashing.MD5 == null) {
      Hashing.MD5 = Hashing.messageDigest("MD5");
    }

    return Hashing.MD5.get().digest(input);
  }

  public static byte[] sha1(String input) {
    return Hashing.sha1(input.getBytes(StandardCharsets.UTF_8));
  }

  public static byte[] sha1(byte[] input) {
    if (Hashing.SHA1 == null) {
      Hashing.SHA1 = Hashing.messageDigest("SHA-1");
    }

    return Hashing.SHA1.get().digest(input);
  }

  public static byte[] sha256(String input) {
    return Hashing.sha256(input.getBytes(StandardCharsets.UTF_8));
  }

  public static byte[] sha256(byte[] input) {
    if (Hashing.SHA256 == null) {
      Hashing.SHA256 = Hashing.messageDigest("SHA-256");
    }

    return Hashing.SHA256.get().digest(input);
  }

  public static byte[] sha512(String input) {
    return Hashing.sha512(input.getBytes(StandardCharsets.UTF_8));
  }

  public static byte[] sha512(byte[] input) {
    if (Hashing.SHA512 == null) {
      Hashing.SHA512 = Hashing.messageDigest("SHA-512");
    }

    return Hashing.SHA512.get().digest(input);
  }

  private static ThreadLocal<MessageDigest> messageDigest(String algorithm) {
    return ThreadLocal.withInitial(() -> {
      try {
        return MessageDigest.getInstance(algorithm);
      } catch (NoSuchAlgorithmException e) {
        throw new RuntimeException(e);
      }
    });
  }

  public static byte[] hmacSHA1(byte[] key, byte[] input) {
    if (Hashing.HMAC_SHA1 == null) {
      Hashing.HMAC_SHA1 = Hashing.mac("HmacSHA1");
    }

    try {
      Mac mac = Hashing.HMAC_SHA1.get();
      mac.init(new SecretKeySpec(key, "HmacSHA1"));
      return mac.doFinal(input);
    } catch (InvalidKeyException e) {
      throw new RuntimeException(e);
    }
  }

  @SuppressWarnings("SameParameterValue")
  private static ThreadLocal<Mac> mac(String algorithm) {
    return ThreadLocal.withInitial(() -> {
      try {
        return Mac.getInstance(algorithm);
      } catch (NoSuchAlgorithmException e) {
        throw new RuntimeException(e);
      }
    });
  }
}
