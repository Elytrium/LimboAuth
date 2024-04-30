/*
 * Copyright (C) 2024 Elytrium
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
import net.elytrium.limboauth.Settings;
import org.bouncycastle.crypto.generators.Argon2BytesGenerator;
import org.bouncycastle.crypto.macs.SipHash;
import org.bouncycastle.crypto.params.Argon2Parameters;

public class AdvancedHashing {

  private static ThreadLocal<Argon2BytesGenerator> ARGON2;
  private static ThreadLocal<SipHash> SIP_HASH;

  public static byte[] argon2(Argon2Parameters parameters, int hashLength, String input) {
    if (AdvancedHashing.ARGON2 == null) {
      AdvancedHashing.ARGON2 = ThreadLocal.withInitial(Argon2BytesGenerator::new);
    }

    byte[] result = new byte[hashLength];
    Argon2BytesGenerator argon2 = AdvancedHashing.ARGON2.get();
    argon2.init(parameters);
    argon2.generateBytes(input.getBytes(StandardCharsets.UTF_8), result);
    return result;
  }

  // A little bit of hardcode
  public static long sipHash(byte[] input1, long input2) {
    if (AdvancedHashing.SIP_HASH == null) {
      AdvancedHashing.SIP_HASH = ThreadLocal.withInitial(() -> {
        SipHash sipHash = new SipHash();
        sipHash.init(Settings.HEAD.mod.verifyKey);
        return sipHash;
      });
    }

    SipHash mac = AdvancedHashing.SIP_HASH.get();
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
}
