package net.elytrium.limboauth.totp;

import java.util.concurrent.ThreadLocalRandom;

public class TimeBasedOneTimePassword {

  private static byte[] generateSecret() {
    ThreadLocalRandom random = ThreadLocalRandom.current();
    byte[] result = new byte[16];
    random.nextBytes(result);
    for (int i = 0; i < 16; ++i) {
      int nextInt = Math.abs(result[i] % 32);
      result[i] = nextInt < 26 ? (byte) ('A' + nextInt) : (byte) ('2' + (nextInt - 26));
    }

    return result;
  }

  private static byte[] generateRecovery() {
    byte[] result = new byte[17];
    ThreadLocalRandom.current().nextBytes(result);
    for (int i = result.length - 1; i >= 0; --i) {
      int nextInt = result[i] % 27;
      result[i] = (byte) Math.min(Math.max((nextInt < 0 ? 'z' + 1 : 'A' - 1) + nextInt, 'A'), 'z');
    }

    result[1] = 'J';
    result[5] = '-';
    result[7] = 'K';
    result[11] = '-';
    result[13] = 'R';
    return result;
  }
}
