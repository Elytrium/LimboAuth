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

// TODO: other strategies (contains, etc.)
public enum CheckPasswordStrategy {

  NONE {
    @Override
    public boolean checkPasswordStrength(UnsafePasswords passwords, String password) {
      return true;
    }
  },
  EXACT {
    @Override
    public boolean checkPasswordStrength(UnsafePasswords passwords, String password) {
      return !passwords.contains(password);
    }
  };

  public abstract boolean checkPasswordStrength(UnsafePasswords passwords, String password);
}
