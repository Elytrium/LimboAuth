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

package net.elytrium.limboauth.password;

import it.unimi.dsi.fastutil.objects.ObjectOpenHashSet;
import java.io.IOException;
import java.io.InputStream;
import java.nio.file.Files;
import java.nio.file.Path;
import net.elytrium.limboauth.LimboAuth;
import net.elytrium.limboauth.Settings;

public final class UnsafePasswords extends ObjectOpenHashSet<String> {

  public UnsafePasswords(LimboAuth plugin) {
    try {
      Path path = plugin.getDataDirectory().resolve(Settings.HEAD.unsafePasswordsFile);
      if (Files.notExists(path)) {
        try (InputStream unsafePasswords = LimboAuth.class.getResourceAsStream("/unsafe_passwords.txt")) {
          if (unsafePasswords == null) {
            LimboAuth.LOGGER.warn("Could not find inner unsafe_passwords.txt, an empty file will be created");
            Files.createFile(path);
          } else {
            Files.copy(unsafePasswords, path);
          }
        }
      }

      this.addAll(Files.readAllLines(path));
    } catch (IOException e) {
      throw new RuntimeException(e);
    }
  }
}
