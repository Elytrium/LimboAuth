package net.elytrium.limboauth.utils;

import java.nio.charset.StandardCharsets;
import java.security.InvalidKeyException;
import java.security.MessageDigest;
import java.security.NoSuchAlgorithmException;
import javax.crypto.Mac;
import javax.crypto.spec.SecretKeySpec;
import org.bouncycastle.crypto.generators.Argon2BytesGenerator;
import org.bouncycastle.crypto.macs.SipHash;
import org.bouncycastle.crypto.params.Argon2Parameters;
import org.bouncycastle.crypto.params.KeyParameter;

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
      Hashing.SHA1 = Hashing.getInstance("SHA-1");
    }

    return Hashing.digest(Hashing.SHA1, input);
  }

  public static String sha256(String input) {
    if (Hashing.SHA256 == null) {
      Hashing.SHA256 = Hashing.getInstance("SHA-256");
    }

    return Hex.encode2String(Hashing.digest(Hashing.SHA256, input));
  }

  public static String sha512(String input) {
    if (Hashing.SHA512 == null) {
      Hashing.SHA512 = Hashing.getInstance("SHA-512");
    }

    return Hex.encode2String(Hashing.digest(Hashing.SHA512, input));
  }

  public static String md5AsHex(String input) {
    return Hex.encode2String(Hashing.md5(input));
  }

  public static byte[] md5(String input) {
    if (Hashing.MD5 == null) {
      Hashing.MD5 = Hashing.getInstance("MD5");
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

  public static long sipHash(KeyParameter key, byte[] input1, byte[] input2) {
    if (Hashing.SIP_HASH == null) {
      Hashing.SIP_HASH = ThreadLocal.withInitial(SipHash::new);
    }

    SipHash sipHash = Hashing.SIP_HASH.get();
    sipHash.init(key);
    // A little bit of hardcode.
    sipHash.update(input1, 0, input1.length);
    sipHash.update(input2, 0, 8);
    return sipHash.doFinal();
  }

  public static byte[] hmacSHA1(byte[] key, byte[] input) {
    if (Hashing.HMAC_SHA1 == null) {
      Hashing.HMAC_SHA1 = ThreadLocal.withInitial(() -> {
        try {
          return Mac.getInstance("HmacSHA1");
        } catch (NoSuchAlgorithmException e) {
          throw new RuntimeException(e);
        }
      });
    }

    try {
      Mac hmacSHA1 = Hashing.HMAC_SHA1.get();
      hmacSHA1.init(new SecretKeySpec(key, "HmacSHA1"));
      return hmacSHA1.doFinal(input);
    } catch (InvalidKeyException e) {
      throw new RuntimeException(e);
    }
  }

  private static ThreadLocal<MessageDigest> getInstance(String algorithm) {
    return ThreadLocal.withInitial(() -> {
      try {
        return MessageDigest.getInstance(algorithm);
      } catch (NoSuchAlgorithmException e) {
        throw new RuntimeException(e);
      }
    });
  }

  private static byte[] digest(ThreadLocal<MessageDigest> threadLocalAlgorithm, String input) {
    return Hashing.digest(threadLocalAlgorithm, input.getBytes(StandardCharsets.UTF_8));
  }

  private static byte[] digest(ThreadLocal<MessageDigest> digest, byte[] input) {
    MessageDigest algorithm = digest.get();
    try {
      return algorithm.digest(input);
    } finally {
      // Not all the engines are resetting themselves after digest.
      algorithm.reset();
    }
  }
}
