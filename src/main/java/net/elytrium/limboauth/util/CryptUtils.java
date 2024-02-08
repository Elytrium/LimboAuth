/*
 * Copyright (C) 2021 - 2024 Elytrium
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

package net.elytrium.limboauth.util;

import at.favre.lib.crypto.bcrypt.BCrypt;
import java.nio.charset.StandardCharsets;
import net.elytrium.limboauth.Settings;
import net.elytrium.limboauth.migration.MigrationHash;
import net.elytrium.limboauth.model.RegisteredPlayer;

public class CryptUtils {
  private static final BCrypt.Verifyer HASH_VERIFIER = BCrypt.verifyer();

  public static boolean checkPassword(String password, RegisteredPlayer player) {
    MigrationHash migrationHash = Settings.IMP.MAIN.MIGRATION_HASH;

    String hash = player.getHash();

    boolean isCorrect = HASH_VERIFIER.verify(
        password.getBytes(StandardCharsets.UTF_8),
        hash.getBytes(StandardCharsets.UTF_8)
    ).verified;

    if (!isCorrect && migrationHash != null) {

      isCorrect = migrationHash.checkPassword(hash, password);

      if (isCorrect) {
        player.setPassword(password);
      }

    }

    return isCorrect;
  }
}
