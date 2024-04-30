/*
 * Copyright (C) 2021-2024 Elytrium
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

package net.elytrium.limboauth.password;

import java.nio.charset.StandardCharsets;
import java.util.Arrays;
import java.util.Base64;
import net.elytrium.limboauth.utils.AdvancedHashing;
import net.elytrium.limboauth.utils.HexHashing;
import org.bouncycastle.crypto.generators.OpenBSDBCrypt;
import org.bouncycastle.crypto.params.Argon2Parameters;

@SuppressWarnings("unused")
public enum MigrationHash { // TODO replace split with indexOf

  SHA256_NO_SALT { // SHA$hash

    @Override
    protected boolean checkPassword(int offset, String hash, String password) {
      String[] args = hash.split("\\$");
      return args.length - offset == 2 && args[offset + 1].equals(HexHashing.sha256(password));
    }
  },
  SHA256_MOONAUTH { // SHA$hash

    @Override
    protected boolean checkPassword(int offset, String hash, String password) {
      String[] args = hash.split("\\$");
      return args.length - offset == 2 && args[offset + 1].equals(HexHashing.sha256(HexHashing.sha256(password)));
    }
  },
  SHA256 { // SHA$salt$hash

    @Override
    protected boolean checkPassword(int offset, String hash, String password) {
      String[] args = hash.split("\\$");
      return args.length - offset == 3 && args[offset + 2].equals(HexHashing.sha256(password + args[offset + 1]));
    }
  },
  SHA256_AUTHME { // SHA$salt$hash

    @Override
    protected boolean checkPassword(int offset, String hash, String password) {
      String[] args = hash.split("\\$");
      return args.length - offset == 3 && args[offset + 2].equals(HexHashing.sha256(HexHashing.sha256(password) + args[offset + 1]));
    }
  },

  SHA512_NO_SALT { // SHA$hash

    @Override
    protected boolean checkPassword(int offset, String hash, String password) {
      String[] args = hash.split("\\$");
      return args.length - offset == 2 && args[offset + 1].equals(HexHashing.sha512(password));
    }
  },
  SHA512 { // SHA$salt$hash

    @Override
    protected boolean checkPassword(int offset, String hash, String password) {
      String[] args = hash.split("\\$");
      return args.length - offset == 3 && args[offset + 2].equals(HexHashing.sha512(password + args[offset + 1]));
    }
  },
  SHA512_DBA { // SHA$salt$hash

    @Override
    protected boolean checkPassword(int offset, String hash, String password) {
      String[] args = hash.split("\\$");
      return args.length - offset == 3 && args[offset + 2].equals(HexHashing.sha512(HexHashing.sha512(password) + args[offset + 1]));
    }
  },
  SHA512_REVERSED { // SHA$hash$salt

    @Override
    protected boolean checkPassword(int offset, String hash, String password) {
      String[] args = hash.split("\\$");
      return args.length - offset == 3 && args[offset + 1].equals(HexHashing.sha512(password + args[offset + 2]));
    }
  },
  SHA512_NLOGIN { // SHA$hash$salt

    @Override
    protected boolean checkPassword(int offset, String hash, String password) {
      String[] args = hash.split("\\$");
      return args.length - offset == 3 && args[offset + 1].equals(HexHashing.sha512(HexHashing.sha512(password) + args[offset + 2]));
    }
  },

  ARGON2 {
    @Override
    protected boolean checkPassword(int parameter, String hash, String password) {
      String[] parameters = hash.split("\\$");
      int parametersLength = parameters.length - parameter;
      if (parametersLength != 4 && parametersLength != 5) {
        return false;
      }

      // Stupid checkstyle can't handle new switch statements properly
      // CHECKSTYLE.OFF: WhitespaceAround
      // CHECKSTYLE.OFF: Indentation
      Argon2Parameters.Builder builder = new Argon2Parameters.Builder(switch (parameters[parameter]) {
        case "argon2d" -> Argon2Parameters.ARGON2_d;
        case "argon2i" -> Argon2Parameters.ARGON2_i;
        case "argon2id" -> Argon2Parameters.ARGON2_id;
        default -> throw new IllegalArgumentException("Invalid algorithm type: " + parameters[parameter]);
      });
      // CHECKSTYLE.ON: Indentation
      // CHECKSTYLE.ON: WhitespaceAround

      if (parameters[++parameter].startsWith("v=")) {
        builder.withVersion(Integer.parseInt(parameters[parameter++].substring(2)));
      }

      String[] performanceParameters = parameters[parameter++].split(",");
      if (performanceParameters.length != 3) {
        throw new IllegalArgumentException("Amount of performance parameters invalid");
      }

      if (!performanceParameters[0].startsWith("m=")) {
        throw new IllegalArgumentException("Invalid memory parameter");
      }
      builder.withMemoryAsKB(Integer.parseInt(performanceParameters[0].substring(2)));

      if (!performanceParameters[1].startsWith("t=")) {
        throw new IllegalArgumentException("Invalid iterations parameter");
      }
      builder.withIterations(Integer.parseInt(performanceParameters[1].substring(2)));

      if (!performanceParameters[2].startsWith("p=")) {
        throw new IllegalArgumentException("Invalid parallelity parameter");
      }
      builder.withParallelism(Integer.parseInt(performanceParameters[2].substring(2)));

      Base64.Decoder base64Decoder = Base64.getDecoder();
      builder.withSalt(base64Decoder.decode(parameters[parameter++]));
      byte[] decodedHash = base64Decoder.decode(parameters[parameter]);
      return Arrays.equals(decodedHash, AdvancedHashing.argon2(builder.build(), decodedHash.length, password));
    }
  },
  MD5 {
    @Override
    protected boolean checkPassword(int offset, String hash, String password) {
      return hash.equals(HexHashing.md5(password));
    }
  },
  BCRYPT_JPREMIUM {
    @Override
    protected boolean checkPassword(int offset, String hash, String password) {
      return OpenBSDBCrypt.checkPassword(hash.replace("BCRYPT$", "$2a$"), password.getBytes(StandardCharsets.UTF_8));
    }
  },
  PLAINTEXT {
    @Override
    protected boolean checkPassword(int offset, String hash, String password) {
      return hash.equals(password);
    }
  };

  public final boolean checkPassword(String hash, String password) {
    if (hash.isEmpty()) {
      return false;
    }

    return this.checkPassword(hash.charAt(0) == '$' ? 1 : 0, hash, password);
  }

  protected abstract boolean checkPassword(int offset, String hash, String password);
}
