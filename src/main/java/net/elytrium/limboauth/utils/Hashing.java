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
      Hashing.SHA1 = Hashing.messageDigest("SHA-1");
    }

    return Hashing.digest(Hashing.SHA1, input);
  }

  public static String sha256(String input) {
    if (Hashing.SHA256 == null) {
      Hashing.SHA256 = Hashing.messageDigest("SHA-256");
    }

    return Hex.string(Hashing.digest(Hashing.SHA256, input));
  }

  public static String sha512(String input) {
    if (Hashing.SHA512 == null) {
      Hashing.SHA512 = Hashing.messageDigest("SHA-512");
    }

    return Hex.string(Hashing.digest(Hashing.SHA512, input));
  }

  public static String md5AsHex(String input) {
    return Hex.string(Hashing.md5(input));
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

  public static long sipHash(KeyParameter key, byte[] input1, byte[] input2) {
    if (Hashing.SIP_HASH == null) {
      Hashing.SIP_HASH = ThreadLocal.withInitial(SipHash::new);
    }

    SipHash mac = Hashing.SIP_HASH.get();
    mac.init(key);
    // A little bit of hardcode.
    mac.update(input1, 0, input1.length);
    mac.update(input2, 0, 8);
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
      // Not all the engines are resetting themselves after digest. (SHA-1 at least, see sun.security.provider.SHA#implDigest)
      algorithm.reset();
    }
  }
}
