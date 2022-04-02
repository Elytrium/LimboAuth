/*
 * Copyright (C) 2021 - 2022 Elytrium
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

package net.elytrium.limboauth;

import java.io.File;
import java.util.List;
import net.elytrium.limboauth.config.Config;
import net.kyori.adventure.title.Title;
import net.kyori.adventure.util.Ticks;

public class Settings extends Config {

  @Ignore
  public static final Settings IMP = new Settings();

  @Final
  public String VERSION = BuildConstants.AUTH_VERSION;

  public String PREFIX = "LimboAuth &6>>&f";

  @Create
  public MAIN MAIN;

  public static class MAIN {

    @Comment("Maximum time for player to authenticate in milliseconds. If the player stays on the auth limbo for longer than this time, then the player will be kicked.")
    public int AUTH_TIME = 60000;
    public boolean ENABLE_BOSSBAR = true;
    @Comment("Available colors: PINK, BLUE, RED, GREEN, YELLOW, PURPLE, WHITE")
    public String BOSSBAR_COLOR = "RED";
    @Comment("Available overlays: PROGRESS, NOTCHED_6, NOTCHED_10, NOTCHED_12, NOTCHED_20")
    public String BOSSBAR_OVERLAY = "NOTCHED_20";
    public int MIN_PASSWORD_LENGTH = 4;
    @Comment("Max password length for the BCrypt hashing algorithm, which is used in this plugin, can't be higher than 71. You can set a lower value than 71.")
    public int MAX_PASSWORD_LENGTH = 71;
    public boolean CHECK_PASSWORD_STRENGTH = true;
    public String UNSAFE_PASSWORDS_FILE = "unsafe_passwords.txt";
    public boolean ONLINE_MODE_NEED_AUTH = true;
    @Comment("Needs floodgate plugin.")
    public boolean FLOODGATE_NEED_AUTH = true;
    @Comment("TOTALLY disables hybrid auth feature")
    public boolean FORCE_OFFLINE_MODE = false;
    @Comment("Forces all players to get offline uuid")
    public boolean FORCE_OFFLINE_UUID = false;
    @Comment("Delay in milliseconds before sending auth-confirming titles and messages to the player. (login-premium-title, login-floodgate, etc.)")
    public int PREMIUM_AND_FLOODGATE_MESSAGES_DELAY = 1250;
    @Comment({
        "Forcibly set player's UUID to the value from the database",
        "If the player had the cracked account, and switched to the premium account, the cracked UUID will be used."
    })
    public boolean SAVE_UUID = true;
    public boolean ENABLE_TOTP = true;
    public boolean TOTP_NEED_PASSWORD = true;
    public boolean REGISTER_NEED_REPEAT_PASSWORD = true;
    public boolean CHANGE_PASSWORD_NEED_OLD_PASSWORD = true;
    @Comment("This prefix will be added to offline mode players nickname")
    public String OFFLINE_MODE_PREFIX = "";
    @Comment("This prefix will be added to online mode players nickname")
    public String ONLINE_MODE_PREFIX = "";
    @Comment({
        "If you want to migrate your database from another plugin, which is not using BCrypt.",
        "You can set an old hash algorithm to migrate from.",
        "AUTHME - AuthMe SHA256(SHA256(password) + salt) that looks like $SHA$salt$hash (AuthMe, MoonVKAuth, DSKAuth, DBA)",
        "AUTHME_NP - AuthMe SHA256(SHA256(password) + salt) that looks like SHA$salt$hash (JPremium)",
        "SHA256_NP - SHA256(password) that looks like SHA$salt$hash",
        "SHA256_P - SHA256(password) that looks like $SHA$salt$hash",
        "SHA512_NP - SHA512(password) that looks like SHA$salt$hash",
        "SHA512_P - SHA512(password) that looks like $SHA$salt$hash",
        "SHA512_DBA - DBA plugin SHA512(SHA512(password) + salt) that looks like SHA$salt$hash (DBA, JPremium)",
        "MD5 - Basic md5 hash",
        "ARGON2 - Argon2 hash that looks like $argon2i$v=1234$m=1234,t=1234,p=1234$hash",
        "MOON_SHA256 - Moon SHA256(SHA256(password)) that looks like $SHA$hash (no salt)",
    })
    public String MIGRATION_HASH = "";
    @Comment("Available dimensions: OVERWORLD, NETHER, THE_END")
    public String DIMENSION = "THE_END";
    public long PURGE_CACHE_MILLIS = 3600000;
    public long PURGE_PREMIUM_CACHE_MILLIS = 28800000;
    @Comment("QR Generator URL, set {data} placeholder")
    public String QR_GENERATOR_URL = "https://api.qrserver.com/v1/create-qr-code/?data={data}&size=200x200&ecc=M&margin=30";
    public String TOTP_ISSUER = "LimboAuth by Elytrium";
    public int BCRYPT_COST = 10;
    public int LOGIN_ATTEMPTS = 3;
    public int IP_LIMIT_REGISTRATIONS = 3;
    public int TOTP_RECOVERY_CODES_AMOUNT = 16;
    @Comment("Time in milliseconds, when ip limit works, set to 0 for disable.")
    public long IP_LIMIT_VALID_TIME = 21600000;
    @Comment({
        "Regex of allowed nicknames",
        "^ means the start of the line, $ means the end of the line",
        "[A-Za-z0-9_] is a character set of A-Z, a-z, 0-9 and _",
        "{3,16} means that allowed length is from 3 to 16 chars"
    })
    public String ALLOWED_NICKNAME_REGEX = "^[A-Za-z0-9_]{3,16}$";

    public boolean LOAD_WORLD = false;
    @Comment("World file type: schematic")
    public String WORLD_FILE_TYPE = "schematic";
    public String WORLD_FILE_PATH = "world.schematic";
    @Comment({
        "Custom isPremium URL",
        "You can use Mojang one's API (set by default)",
        "Or CloudFlare one's: https://api.ashcon.app/mojang/v1/user/%s",
        "Or use this code to make your own API: https://blog.cloudflare.com/minecraft-api-with-workers-coffeescript/",
        "Or implement your own API, it should just respond with HTTP code 200 only if the player is premium"
    })
    public String ISPREMIUM_AUTH_URL = "https://api.mojang.com/users/profiles/minecraft/%s";

    @Comment({
        "If Mojang rate-limits your server, we cannot determine if the player is premium or not",
        "This option allows you to choose whether every player will be defined as premium or as cracked while Mojang is rate-limiting the server",
        "True - as premium; False - as cracked"
    })
    public boolean ON_RATE_LIMIT_PREMIUM = true;

    public List<String> REGISTER_COMMAND = List.of("/r", "/reg", "/register");
    public List<String> LOGIN_COMMAND = List.of("/l", "/log", "/login");
    public List<String> TOTP_COMMAND = List.of("/2fa", "/totp");

    @Create
    public Settings.MAIN.WORLD_COORDS WORLD_COORDS;

    public static class WORLD_COORDS {

      public int X = 0;
      public int Y = 0;
      public int Z = 0;
    }

    @Create
    public Settings.MAIN.CRACKED_TITLE_SETTINGS CRACKED_TITLE_SETTINGS;

    public static class CRACKED_TITLE_SETTINGS {

      public int FADE_IN = 10;
      public int STAY = 70;
      public int FADE_OUT = 20;
      public boolean CLEAR_AFTER_LOGIN = false;

      public Title.Times toTimes() {
        return Title.Times.times(Ticks.duration(this.FADE_IN), Ticks.duration(this.STAY), Ticks.duration(this.FADE_OUT));
      }

    }

    @Create
    public Settings.MAIN.PREMIUM_TITLE_SETTINGS PREMIUM_TITLE_SETTINGS;

    public static class PREMIUM_TITLE_SETTINGS {

      public int FADE_IN = 10;
      public int STAY = 70;
      public int FADE_OUT = 20;

      public Title.Times toTimes() {
        return Title.Times.times(Ticks.duration(this.FADE_IN), Ticks.duration(this.STAY), Ticks.duration(this.FADE_OUT));
      }
    }

    /*
    @Create
    public Settings.MAIN.EVENTS_PRIORITIES EVENTS_PRIORITIES;

    @Comment("Available priorities: FIRST, EARLY, NORMAL, LATE, LAST")
    public static class EVENTS_PRIORITIES {

      public String PRE_LOGIN = "NORMAL";
      public String LOGIN_LIMBO_REGISTER = "NORMAL";
      public String SAFE_GAME_PROFILE_REQUEST = "NORMAL";
    }
    */

    @Create
    public MAIN.STRINGS STRINGS;

    @Comment("You can left blank the fields marked with \"*\" comment.")
    public static class STRINGS {

      public String RELOAD = "{PRFX} &aReloaded successfully!";
      public String RELOAD_FAILED = "{PRFX} &cReload failed, check console for details.";
      public String ERROR_OCCURRED = "{PRFX} &cAn internal error has occurred!";

      public String NOT_PLAYER = "{PRFX} &cСonsole is not allowed to execute this command!";
      public String NOT_REGISTERED = "{PRFX} &cYou are not registered or your account is &6PREMIUM!";
      public String CRACKED_COMMAND = "{PRFX}{NL}&aYou can not use this command since your account is &6PREMIUM&a!";
      public String WRONG_PASSWORD = "{PRFX} &cPassword is wrong!";

      public String NICKNAME_INVALID_KICK = "{PRFX}{NL}&cYour nickname contains forbidden characters. Please, change your nickname!";

      @Comment("6 hours by default in ip-limit-valid-time")
      public String IP_LIMIT = "{PRFX} &cYour IP has reached max registered accounts. If this is an error, restart your router, or wait about 6 hours.";
      public String WRONG_NICKNAME_CASE_KICK = "{PRFX}{NL}&cThe case of your nickname is wrong. Nickname is CaSe SeNsItIvE.";

      public String BOSSBAR = "{PRFX} You have &6{0} &fseconds left to log in.";
      public String TIMES_UP = "{PRFX}{NL}&cAuthorization time is up.";

      @Comment("*")
      public String LOGIN_PREMIUM = "{PRFX} You've been logged in automatically using the premium account!";
      @Comment("*")
      public String LOGIN_PREMIUM_TITLE = "{PRFX} Welcome!";
      @Comment("*")
      public String LOGIN_PREMIUM_SUBTITLE = "&aYou has been logged in as premium player!";
      @Comment("*")
      public String LOGIN_FLOODGATE = "{PRFX} You've been logged in automatically using the bedrock account!";
      @Comment("*")
      public String LOGIN_FLOODGATE_TITLE = "{PRFX} Welcome!";
      @Comment("*")
      public String LOGIN_FLOODGATE_SUBTITLE = "&aYou has been logged in as bedrock player!";

      public String LOGIN = "{PRFX} &aPlease, login using &6/login <password>&a, you have &6{0} &aattempts.";
      public String LOGIN_WRONG_PASSWORD = "{PRFX} &cYou''ve entered the wrong password, you have &6{0} &cattempts left.";
      public String LOGIN_WRONG_PASSWORD_KICK = "{PRFX}{NL}&cYou've entered the wrong password numerous times!";
      public String LOGIN_SUCCESSFUL = "{PRFX} &aSuccessfully logged in!";
      @Comment("*")
      public String LOGIN_TITLE = "&fPlease, login using &6/login <password>&a.";
      @Comment("*")
      public String LOGIN_SUBTITLE = "&aYou have &6{0} &aattempts.";
      @Comment("*")
      public String LOGIN_SUCCESSFUL_TITLE = "{PRFX}";
      @Comment("*")
      public String LOGIN_SUCCESSFUL_SUBTITLE = "&aSuccessfully logged in!";

      @Comment("Or if register-need-repeat-password set to false remove the \"<repeat password>\" part.")
      public String REGISTER = "{PRFX} Please, register using &6/register <password> <repeat password>";
      public String REGISTER_DIFFERENT_PASSWORDS = "{PRFX} &cThe entered passwords differ from each other!";
      public String REGISTER_PASSWORD_TOO_SHORT = "{PRFX} &cYou entered too short password, use a different one!";
      public String REGISTER_PASSWORD_TOO_LONG = "{PRFX} &cYou entered too long password, use a different one!";
      public String REGISTER_PASSWORD_UNSAFE = "{PRFX} &cYour password is unsafe, use a different one!";
      public String REGISTER_SUCCESSFUL = "{PRFX} &aSuccessfully registered!";
      @Comment("*")
      public String REGISTER_TITLE = "{PRFX}";
      @Comment("*")
      public String REGISTER_SUBTITLE = "&aPlease, register using &6/register <password> <repeat password>";
      @Comment("*")
      public String REGISTER_SUCCESSFUL_TITLE = "{PRFX}";
      @Comment("*")
      public String REGISTER_SUCCESSFUL_SUBTITLE = "&aSuccessfully registered!";

      public String UNREGISTER_SUCCESSFUL = "{PRFX}{NL}&aSuccessfully unregistered!";
      public String UNREGISTER_USAGE = "{PRFX} Usage: &6/unregister <current password> confirm";

      public String PREMIUM_SUCCESSFUL = "{PRFX}{NL}&aSuccessfully changed account state to &6PREMIUM&a!";
      public String ALREADY_PREMIUM = "{PRFX} &cYour account is already &6PREMIUM&c!";
      public String NOT_PREMIUM = "{PRFX} &cYour account is not &6PREMIUM&c!";
      public String PREMIUM_USAGE = "{PRFX} Usage: &6/premium <current password> confirm";

      public String EVENT_CANCELLED = "{PRFX} Authorization event was cancelled";

      public String FORCE_UNREGISTER_SUCCESSFUL = "{PRFX} &6{0} &asuccessfully unregistered!";
      public String FORCE_UNREGISTER_KICK = "{PRFX}{NL}&aYou have been unregistered by administrator!";
      public String FORCE_UNREGISTER_NOT_SUCCESSFUL = "{PRFX} &cUnable to unregister &6{0}&c. Most likely this player has never been on this server.";
      public String FORCE_UNREGISTER_USAGE = "{PRFX} Usage: &6/forceunregister <nickname>";

      public String CHANGE_PASSWORD_SUCCESSFUL = "{PRFX} &aSuccessfully changed password!";
      @Comment("Or if change-password-need-old-pass set to false remove the \"<old password>\" part.")
      public String CHANGE_PASSWORD_USAGE = "{PRFX} Usage: &6/changepassword <old password> <new password>";

      public String FORCE_CHANGE_PASSWORD_SUCCESSFUL = "{PRFX} &aSuccessfully changed password for player &6{0}&a!";
      public String FORCE_CHANGE_PASSWORD_MESSAGE = "{PRFX} &aYour password has been changed to &6{0} &aby administator!";
      public String FORCE_CHANGE_PASSWORD_NOT_SUCCESSFUL = "{PRFX} &cUnable to change password for &6{0}&c. Most likely this player has never been on this server.";
      public String FORCE_CHANGE_PASSWORD_USAGE = "{PRFX} Usage: &6/forcechangepassword <nickname> <new password>";

      public String TOTP = "{PRFX} Please, enter your 2FA key using &6/2fa <key>";
      @Comment("*")
      public String TOTP_TITLE = "{PRFX}";
      @Comment("*")
      public String TOTP_SUBTITLE = "&aEnter your 2FA key using &6/2fa <key>";
      public String TOTP_SUCCESSFUL = "{PRFX} &aSuccessfully enabled 2FA!";
      public String TOTP_DISABLED = "{PRFX} &aSuccessfully disabled 2FA!";
      @Comment("Or if totp-need-pass set to false remove the \"<current password>\" part.")
      public String TOTP_USAGE = "{PRFX} Usage: &6/2fa enable <current password>&f or &6/2fa disable <totp key>&f.";
      public String TOTP_WRONG = "{PRFX} &cWrong 2FA key!";
      public String TOTP_ALREADY_ENABLED = "{PRFX} &c2FA is already enabled. Disable it using &6/2fa disable <key>&c.";
      public String TOTP_QR = "{PRFX} Click here to open 2FA QR code in browser.";
      public String TOTP_TOKEN = "{PRFX} &aYour 2FA token &7(Click to copy)&a: &6{0}";
      public String TOTP_RECOVERY = "{PRFX} &aYour recovery codes &7(Click to copy)&a: &6{0}";

      public String DESTROY_SESSION_SUCCESSFUL = "{PRFX} &eYour session is now destroyed, you'll need to log in again after reconnecting.";
    }

    @Create
    public MAIN.AUTH_COORDS AUTH_COORDS;

    public static class AUTH_COORDS {

      public double X = 0;
      public double Y = 0;
      public double Z = 0;
      public double YAW = 0;
      public double PITCH = 0;
    }
  }

  @Create
  public DATABASE DATABASE;

  @Comment("Database settings")
  public static class DATABASE {

    @Comment("Database type: mysql, postgresql or h2.")
    public String STORAGE_TYPE = "h2";

    @Comment("Settings for Network-based database (like MySQL, PostgreSQL): ")
    public String HOSTNAME = "127.0.0.1:3306";
    public String USER = "user";
    public String PASSWORD = "password";
    public String DATABASE = "limboauth";
    public String CONNECTION_PARAMETERS = "?autoReconnect=true&initialTimeout=1&useSSL=false";
  }

  public void reload(File file) {
    if (this.load(file, this.PREFIX)) {
      this.save(file);
    } else {
      this.save(file);
      this.load(file, this.PREFIX);
    }
  }
}
