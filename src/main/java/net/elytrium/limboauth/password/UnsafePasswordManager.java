package net.elytrium.limboauth.password;

import java.io.IOException;
import java.nio.file.Files;
import java.nio.file.Path;
import java.util.Objects;
import java.util.Set;
import net.elytrium.fastutil.objects.ObjectOpenHashSet;
import net.elytrium.limboauth.LimboAuth;
import net.elytrium.limboauth.Settings;

public class UnsafePasswordManager {

  private final Set<String> unsafePasswords;

  public UnsafePasswordManager(LimboAuth plugin) throws IOException {
    Path unsafePasswordsPath = plugin.getDataDirectory().resolve(Settings.HEAD.unsafePasswordsFile);
    if (!unsafePasswordsPath.toFile().exists()) {
      Files.copy(Objects.requireNonNull(this.getClass().getResourceAsStream("/unsafe_passwords.txt")), unsafePasswordsPath);
    }

    this.unsafePasswords = new ObjectOpenHashSet<>(Files.readAllLines(unsafePasswordsPath));
  }

  public boolean exactMatch(String password) {
    return this.unsafePasswords.contains(password);
  }
}
