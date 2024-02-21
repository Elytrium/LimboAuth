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
import net.elytrium.limboauth.Settings;
import org.bouncycastle.crypto.generators.Argon2BytesGenerator;
import org.bouncycastle.crypto.macs.SipHash;
import org.bouncycastle.crypto.params.Argon2Parameters;

public class Hashing {

  private static ThreadLocal<MessageDigest> SHA1;
  private static ThreadLocal<MessageDigest> SHA256;
  private static ThreadLocal<MessageDigest> SHA512;
  private static ThreadLocal<MessageDigest> MD5;
  private static ThreadLocal<Argon2BytesGenerator> ARGON2;
  private static ThreadLocal<SipHash> SIP_HASH;
  private static ThreadLocal<Mac> HMAC_SHA1;

  public static byte[] sha1(byte[] input) {
    if (Hashing.SHA1 == null) {
      Hashing.SHA1 = Hashing.messageDigest("SHA-1");
    }

    return Hashing.digest(Hashing.SHA1, input);
  }

  public static String sha256(String input) {
    if (Hashing.SHA256 == null) {
      Hashing.SHA256 = Hashing.messageDigest("SHA-256");
    }

    return Hex.encodeString(Hashing.digest(Hashing.SHA256, input));
  }

  public static String sha512(String input) {
    if (Hashing.SHA512 == null) {
      Hashing.SHA512 = Hashing.messageDigest("SHA-512");
    }

    return Hex.encodeString(Hashing.digest(Hashing.SHA512, input));
  }

  public static byte[] md5(String input) {
    if (Hashing.MD5 == null) {
      Hashing.MD5 = Hashing.messageDigest("MD5");
    }

    return Hashing.digest(Hashing.MD5, input);
  }

  public static byte[] argon2(Argon2Parameters parameters, int hashLength, String input) {
    if (Hashing.ARGON2 == null) {
      Hashing.ARGON2 = ThreadLocal.withInitial(Argon2BytesGenerator::new);
    }

    byte[] result = new byte[hashLength];
    Argon2BytesGenerator argon2 = Hashing.ARGON2.get();
    argon2.init(parameters);
    argon2.generateBytes(input.getBytes(StandardCharsets.UTF_8), result);
    return result;
  }

  public static long sipHash(byte[] input1, long input2) {
    if (Hashing.SIP_HASH == null) {
      Hashing.SIP_HASH = ThreadLocal.withInitial(() -> {
        SipHash sipHash = new SipHash();
        sipHash.init(Settings.HEAD.mod.verifyKey);
        return sipHash;
      });
    }

    SipHash mac = Hashing.SIP_HASH.get();
    mac.update(input1, 0, input1.length);
    mac.update((byte) ((input2 >>> 56) & 0xFF));
    mac.update((byte) ((input2 >>> 48) & 0xFF));
    mac.update((byte) ((input2 >>> 40) & 0xFF));
    mac.update((byte) ((input2 >>> 32) & 0xFF));
    mac.update((byte) ((input2 >>> 24) & 0xFF));
    mac.update((byte) ((input2 >>> 16) & 0xFF));
    mac.update((byte) ((input2 >>> 8) & 0xFF));
    mac.update((byte) (input2 & 0xFF));
    return mac.doFinal();
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

  private static ThreadLocal<MessageDigest> messageDigest(String algorithm) {
    return ThreadLocal.withInitial(() -> {
      try {
        return MessageDigest.getInstance(algorithm);
      } catch (NoSuchAlgorithmException e) {
        throw new RuntimeException(e);
      }
    });
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

  private static byte[] digest(ThreadLocal<MessageDigest> digest, String input) {
    return Hashing.digest(digest, input.getBytes(StandardCharsets.UTF_8));
  }

  private static byte[] digest(ThreadLocal<MessageDigest> digest, byte[] input) {
    MessageDigest algorithm = digest.get();
    try {
      return algorithm.digest(input);
    } finally {
      // Not all the engines are resetting themselves after digest (SHA-1 at least, see sun.security.provider.SHA#implDigest)
      algorithm.reset();
    }
  }
}
