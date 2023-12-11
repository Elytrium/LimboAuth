package net.elytrium.limboauth.password;

import java.util.function.BiFunction;

// TODO: other strategies
public enum CheckPasswordStrategy {
  NONE((passwordManager, password) -> true),
  EXACT((passwordManager, password) -> !passwordManager.exactMatch(password));

  private final BiFunction<UnsafePasswordManager, String, Boolean> checkPasswordFunction;

  CheckPasswordStrategy(BiFunction<UnsafePasswordManager, String, Boolean> checkPasswordFunction) {
    this.checkPasswordFunction = checkPasswordFunction;
  }

  public boolean checkPasswordStrength(UnsafePasswordManager passwordManager, String password) {
    return this.checkPasswordFunction.apply(passwordManager, password);
  }
}
