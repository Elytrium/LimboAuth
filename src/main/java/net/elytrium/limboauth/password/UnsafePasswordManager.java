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

import java.io.IOException;
import java.nio.file.Files;
import java.nio.file.Path;
import java.util.Objects;
import net.elytrium.fastutil.objects.ObjectOpenHashSet;
import net.elytrium.fastutil.objects.ObjectSet;
import net.elytrium.limboauth.LimboAuth;
import net.elytrium.limboauth.Settings;

public class UnsafePasswordManager {

  private final ObjectSet<String> unsafePasswords;

  public UnsafePasswordManager(LimboAuth plugin) throws IOException {
    Path path = plugin.getDataDirectory().resolve(Settings.HEAD.unsafePasswordsFile);
    if (Files.notExists(path)) {
      Files.copy(Objects.requireNonNull(this.getClass().getResourceAsStream("/unsafe_passwords.txt")), path);
    }

    this.unsafePasswords = new ObjectOpenHashSet<>(Files.readAllLines(path));
  }

  public boolean exactMatch(String password) {
    return this.unsafePasswords.contains(password);
  }
}
