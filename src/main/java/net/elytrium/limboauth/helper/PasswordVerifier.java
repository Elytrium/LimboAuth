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

package net.elytrium.limboauth.helper;

import net.elytrium.limboauth.LimboAuth;
import net.elytrium.limboauth.Settings;

public class PasswordVerifier {
  private final LimboAuth plugin;

  public PasswordVerifier(LimboAuth plugin) {
    this.plugin = plugin;
  }

  public PasswordVerificationResult checkPassword(String[] args) {
    if (this.checkPasswordsRepeat(args) != PasswordVerificationResult.PASSWORD_OK) {
      return this.checkPasswordsRepeat(args);
    }

    return this.checkPassword(args[1]);
  }

  public PasswordVerificationResult checkPassword(String password) {
    if (this.checkPasswordLength(password) != PasswordVerificationResult.PASSWORD_OK) {
      return this.checkPasswordLength(password);
    }

    if (this.checkPasswordStrength(password) != PasswordVerificationResult.PASSWORD_OK) {
      return this.checkPasswordStrength(password);
    }

    return PasswordVerificationResult.PASSWORD_OK;
  }

  public PasswordVerificationResult checkPasswordsRepeat(String[] args) {
    if (!Settings.IMP.MAIN.REGISTER_NEED_REPEAT_PASSWORD || args[1].equals(args[2])) {
      return PasswordVerificationResult.PASSWORD_OK;
    } else {
      return PasswordVerificationResult.PASSWORDS_DOESNT_MATCH;
    }
  }

  public PasswordVerificationResult checkPasswordLength(String password) {
    int length = password.length();
    if (length > Settings.IMP.MAIN.MAX_PASSWORD_LENGTH) {
      return PasswordVerificationResult.PASSWORD_TOO_LONG;
    } else if (length < Settings.IMP.MAIN.MIN_PASSWORD_LENGTH) {
      return PasswordVerificationResult.PASSWORD_TOO_SHORT;
    } else {
      return PasswordVerificationResult.PASSWORD_OK;
    }
  }

  public PasswordVerificationResult checkPasswordStrength(String password) {
    if (Settings.IMP.MAIN.CHECK_PASSWORD_STRENGTH && this.plugin.getUnsafePasswords().contains(password)) {
      return PasswordVerificationResult.PASSWORD_UNSAFE;
    } else {
      return PasswordVerificationResult.PASSWORD_OK;
    }
  }

  public enum PasswordVerificationResult {
    PASSWORDS_DOESNT_MATCH,
    PASSWORD_TOO_LONG,
    PASSWORD_TOO_SHORT,
    PASSWORD_UNSAFE,
    PASSWORD_OK
  }
}
