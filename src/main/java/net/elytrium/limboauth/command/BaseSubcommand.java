package net.elytrium.limboauth.command;

public class BaseSubcommand {

  protected final String[] aliases;

  public BaseSubcommand(String... aliases) {
    this.aliases = aliases;
  }
}
