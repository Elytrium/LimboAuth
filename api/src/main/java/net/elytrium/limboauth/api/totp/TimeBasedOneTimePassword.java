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

package net.elytrium.limboauth.api.totp;

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

  private static byte[] generateRecovery() { // TODO should by hashed?
    byte[] result = new byte[17];
    ThreadLocalRandom.current().nextBytes(result);
    for (int i = result.length - 1; i >= 0; --i) {
      int nextInt = result[i] % 27;
      result[i] = (byte) Math.min(Math.max((nextInt < 0 ? 'z' + 1 : 'A' - 1) + nextInt, 'A'), 'z');
    }

    result[1] = '1';
    result[5] = '-';
    result[7] = '2';
    result[11] = '-';
    result[13] = '3';
    return result;
  }
}
