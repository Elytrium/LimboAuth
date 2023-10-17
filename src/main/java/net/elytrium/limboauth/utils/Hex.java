package net.elytrium.limboauth.utils;

public class Hex {

  private static final byte[] BYTE_TABLE = {
      '0', '1', '2', '3', '4', '5', '6', '7', '8', '9', 'a', 'b', 'c', 'd', 'e', 'f'
  };
  private static final char[] CHAR_TABLE = {
      '0', '1', '2', '3', '4', '5', '6', '7', '8', '9', 'a', 'b', 'c', 'd', 'e', 'f'
  };

  public static byte[] encode2Bytes(byte[] data) {
    byte[] result = new byte[data.length << 1];
    for (int i = 0, j = 0; i < data.length; ++i) {
      result[j++] = Hex.BYTE_TABLE[(data[i] & 0xF0) >>> 4];
      result[j++] = Hex.BYTE_TABLE[data[i] & 0x0F];
    }

    return result;
  }

  public static char[] encode2Chars(byte[] data) {
    char[] result = new char[data.length << 1];
    for (int i = 0, j = 0; i < data.length; ++i) {
      result[j++] = Hex.CHAR_TABLE[(data[i] & 0xF0) >>> 4];
      result[j++] = Hex.CHAR_TABLE[data[i] & 0x0F];
    }

    return result;
  }

  public static String encode2String(byte[] data) {
    return new String(Hex.encode2Chars(data));
  }
}
