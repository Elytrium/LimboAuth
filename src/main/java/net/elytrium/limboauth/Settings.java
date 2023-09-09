/*
 * Copyright (C) 2021 - 2023 Elytrium
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

import java.nio.charset.StandardCharsets;
import java.security.MessageDigest;
import java.security.NoSuchAlgorithmException;
import java.security.SecureRandom;
import java.util.List;
import java.util.Map;
import java.util.Random;
import net.elytrium.commons.kyori.serialization.Serializers;
import net.elytrium.limboapi.api.chunk.Dimension;
import net.elytrium.limboapi.api.file.BuiltInWorldFileType;
import net.elytrium.limboapi.api.player.GameMode;
import net.elytrium.limboauth.command.CommandPermissionState;
import net.elytrium.limboauth.dependencies.DatabaseLibrary;
import net.elytrium.limboauth.migration.MigrationHash;
import net.elytrium.limboauth.utils.BossBarSerializer;
import net.elytrium.limboauth.utils.ComponentReplacer;
import net.elytrium.limboauth.utils.ComponentSerializer;
import net.elytrium.limboauth.utils.TitleReplacer;
import net.elytrium.limboauth.utils.TitleSerializer;
import net.elytrium.serializer.SerializerConfig;
import net.elytrium.serializer.annotations.Comment;
import net.elytrium.serializer.annotations.CommentValue;
import net.elytrium.serializer.annotations.RegisterPlaceholders;
import net.elytrium.serializer.annotations.Serializer;
import net.elytrium.serializer.custom.ClassSerializer;
import net.elytrium.serializer.language.object.YamlSerializable;
import net.kyori.adventure.bossbar.BossBar;
import net.kyori.adventure.text.Component;
import net.kyori.adventure.title.Title;
import net.kyori.adventure.util.Ticks;

public class Settings extends YamlSerializable {

  public static final Settings IMP = new Settings();
  public static final Settings.Messages MESSAGES = Settings.IMP.messages;

  private Settings() {
    super(new SerializerConfig.Builder()
        .registerSerializer(ComponentSerializer.INSTANCE)
        .registerSerializer(TitleSerializer.INSTANCE)
        .registerSerializer(BossBarSerializer.INSTANCE)
        .registerReplacer(ComponentReplacer.INSTANCE)
        .registerReplacer(TitleReplacer.INSTANCE)
        .setCommentValueIndent(1)
        .build()
    );
  }

  public final String version = BuildConfig.VERSION;

  @Comment({
      @CommentValue("Available serializers:"),
      @CommentValue("LEGACY_AMPERSAND - \"&c&lExample &c&9Text\"."),
      @CommentValue("LEGACY_SECTION - \"§c§lExample §c§9Text\"."),
      @CommentValue("MINIMESSAGE - \"<bold><red>Example</red> <blue>Text</blue></bold>\". (https://webui.adventure.kyori.net/)"),
      @CommentValue("GSON - \"[{\"text\":\"Example\",\"bold\":true,\"color\":\"red\"},{\"text\":\" \",\"bold\":true},{\"text\":\"Text\",\"bold\":true,\"color\":\"blue\"}]\". (https://minecraft.tools/en/json_text.php/)"),
      @CommentValue("GSON_COLOR_DOWNSAMPLING - Same as GSON, but uses downsampling."),
  })
  public Serializers serializer = Serializers.LEGACY_AMPERSAND; // TODO сделать по нормальному

  @Comment(@CommentValue("Maximum time for player to authenticate in milliseconds. If the player stays on the auth limbo for longer than this time, then the player will be kicked."))
  public int authTime = 60000;
  public boolean enableBossbar = true;
  public int minPasswordLength = 4;
  @Comment(@CommentValue("Max password length for the BCrypt hashing algorithm, which is used in this plugin, can't be higher than 71. You can set a lower value than 71."))
  public int maxPasswordLength = 71;
  public boolean checkPasswordStrength = true;
  public String unsafePasswordsFile = "unsafe_passwords.txt";
  @Comment({
      @CommentValue("Players with premium nicknames should register/auth if this option is enabled"),
      @CommentValue("Players with premium nicknames must login with a premium Minecraft account if this option is disabled"),
  })
  public boolean onlineModeNeedAuth = true;
  @Comment(@CommentValue("Needs floodgate plugin if disabled."))
  public boolean floodgateNeedAuth = true;
  @Comment(@CommentValue("TOTALLY disables hybrid auth feature"))
  public boolean forceOfflineMode = false;
  @Comment(@CommentValue("Forces all players to get offline uuid"))
  public boolean forceOfflineUuid = false;
  @Comment(@CommentValue("If enabled, the plugin will firstly check whether the player is premium through the local database, and secondly through Mojang API."))
  public boolean checkPremiumPriorityInternal = true;
  @Comment(@CommentValue("Delay in milliseconds before sending auth-confirming titles and messages to the player. (login-premium-title, login-floodgate, etc.)"))
  public int premiumAndFloodgateMessagesDelay = 1250;
  @Comment({
      @CommentValue("Forcibly set player's UUID to the value from the database"),
      @CommentValue("If the player had the cracked account, and switched to the premium account, the cracked UUID will be used."),
  })
  public boolean saveUuid = true;
  @Comment({
      @CommentValue("Saves in the database the accounts of premium users whose login is via online-mode-need-auth: false"),
      @CommentValue("Can be disabled to reduce the size of  stored data in the database"),
  })
  public boolean savePremiumAccounts = true;
  public boolean enableTotp = true;
  public boolean totpNeedPassword = true;
  public boolean registerNeedRepeatPassword = true;
  public boolean changePasswordNeedOldPassword = true;
  @Comment(@CommentValue("Used in unregister and premium commands."))
  public String confirmKeyword = "confirm";
  @Comment(@CommentValue("This prefix will be added to offline mode players nickname"))
  public String offlineModePrefix = "";
  @Comment(@CommentValue("This prefix will be added to online mode players nickname"))
  public String onlineModePrefix = "";
  @Comment({
      @CommentValue("If you want to migrate your database from another plugin, which is not using BCrypt."),
      @CommentValue("You can set an old hash algorithm to migrate from."),
      @CommentValue("AUTHME - AuthMe SHA256(SHA256(password) + salt) that looks like $SHA$salt$hash (AuthMe, MoonVKAuth, DSKAuth, DBA)"),
      @CommentValue("AUTHME_NP - AuthMe SHA256(SHA256(password) + salt) that looks like SHA$salt$hash (JPremium)"),
      @CommentValue("SHA256_NP - SHA256(password) that looks like SHA$salt$hash"),
      @CommentValue("SHA256_P - SHA256(password) that looks like $SHA$salt$hash"),
      @CommentValue("SHA512_NP - SHA512(password) that looks like SHA$salt$hash"),
      @CommentValue("SHA512_P - SHA512(password) that looks like $SHA$salt$hash"),
      @CommentValue("SHA512_DBA - DBA plugin SHA512(SHA512(password) + salt) that looks like SHA$salt$hash (DBA, JPremium)"),
      @CommentValue("MD5 - Basic md5 hash"),
      @CommentValue("ARGON2 - Argon2 hash that looks like $argon2i$v=1234$m=1234,t=1234,p=1234$hash"),
      @CommentValue("MOON_SHA256 - Moon SHA256(SHA256(password)) that looks like $SHA$hash (no salt)"),
      @CommentValue("SHA256_NO_SALT - SHA256(password) that looks like $SHA$hash (NexAuth)"),
      @CommentValue("SHA512_NO_SALT - SHA512(password) that looks like $SHA$hash (NexAuth)"),
      @CommentValue("SHA512_P_REVERSED_HASH - SHA512(password) that looks like $SHA$hash$salt (nLogin)"),
      @CommentValue("SHA512_NLOGIN - SHA512(SHA512(password) + salt) that looks like $SHA$hash$salt (nLogin)"),
      @CommentValue("CRC32C - Basic CRC32C hash"),
      @CommentValue("PLAINTEXT - Plain text"),
  })
  public MigrationHash migrationHash = MigrationHash.AUTHME;
  @Comment(@CommentValue("Available dimensions: OVERWORLD, NETHER, THE_END"))
  public Dimension dimension = Dimension.THE_END;
  public long purgeCacheMillis = 3600000;
  public long purgePremiumCacheMillis = 28800000;
  public long purgeBruteforceCacheMillis = 28800000;
  @Comment(@CommentValue("Used to ban IPs when a possible attacker incorrectly enters the password"))
  public int bruteforceMaxAttempts = 10;
  @Comment(@CommentValue("QR Generator URL, set {data} placeholder"))
  @RegisterPlaceholders("DATA")
  public String qrGeneratorUrl = "https://api.qrserver.com/v1/create-qr-code/?data={DATA}&size=200x200&ecc=M&margin=30";
  public String totpIssuer = "LimboAuth by Elytrium";
  public int bcryptCost = 10;
  public int loginAttempts = 3;
  public int ipLimitRegistrations = 3;
  public int totpRecoveryCodesAmount = 16;
  @Comment(@CommentValue("Time in milliseconds, when ip limit works, set to 0 for disable."))
  public long ipLimitValidTime = 21600000;
  @Comment({
      @CommentValue("Regex of allowed nicknames"),
      @CommentValue("^ means the start of the line, $ means the end of the line"),
      @CommentValue("[A-Za-z0-9_] is a character set of A-Z, a-z, 0-9 and _"),
      @CommentValue("{3,16} means that allowed length is from 3 to 16 chars"),
  })
  public String allowedNicknameRegex = "^[A-Za-z0-9_]{3,16}$";

  public boolean loadWorld = false;
  @Comment({
      @CommentValue("World file type:"),
      @CommentValue(" SCHEMATIC (MCEdit .schematic, 1.12.2 and lower, not recommended)"),
      @CommentValue(" STRUCTURE (structure block .nbt, any Minecraft version is supported, but the latest one is recommended)."),
      @CommentValue(" WORLDEDIT_SCHEM (WorldEdit .schem, any Minecraft version is supported, but the latest one is recommended)."),
  })
  public BuiltInWorldFileType worldFileType = BuiltInWorldFileType.STRUCTURE;
  public String worldFilePath = "world.nbt";
  public boolean disableFalling = true;

  @Comment(@CommentValue("World time in ticks (24000 ticks == 1 in-game day)"))
  public long worldTicks = 1000L;

  @Comment(@CommentValue("World light level (from 0 to 15)"))
  public int worldLightLevel = 15;

  @Comment(@CommentValue("Available: ADVENTURE, CREATIVE, SURVIVAL, SPECTATOR"))
  public GameMode gameMode = GameMode.ADVENTURE;

  @Comment({
      @CommentValue("Custom isPremium URL"),
      @CommentValue("You can use Mojang one's API (set by default)"),
      @CommentValue("Or CloudFlare one's: https://api.ashcon.app/mojang/v2/user/%s"),
      @CommentValue("Or use this code to make your own API: https://blog.cloudflare.com/minecraft-api-with-workers-coffeescript/"),
      @CommentValue("Or implement your own API, it should just respond with HTTP code 200 (see parameters below) only if the player is premium"),
  })
  public String isPremiumAuthUrl = "https://api.mojang.com/users/profiles/minecraft/%s";

  @Comment({
      @CommentValue("Status codes (see the comment above)"),
      @CommentValue("Responses with unlisted status codes will be identified as responses with a server error"),
      @CommentValue("Set 200 if you use using Mojang or CloudFlare API"),
  })
  public List<Integer> statusCodeUserExists = List.of(200);
  @Comment(@CommentValue("Set 204 and 404 if you use Mojang API, 404 if you use CloudFlare API"))
  public List<Integer> statusCodeUserNotExists = List.of(204, 404);
  @Comment(@CommentValue("Set 429 if you use Mojang or CloudFlare API"))
  public List<Integer> statusCodeRateLimit = List.of(429);

  @Comment({
      @CommentValue("Sample Mojang API exists response: {\"name\":\"hevav\",\"id\":\"9c7024b2a48746b3b3934f397ae5d70f\"}"),
      @CommentValue("Sample CloudFlare API exists response: {\"uuid\":\"9c7024b2a48746b3b3934f397ae5d70f\",\"username\":\"hevav\", ...}"),
      @CommentValue(),
      @CommentValue("Sample Mojang API not exists response (sometimes can be empty): {\"path\":\"/users/profiles/minecraft/someletters1234566\",\"errorMessage\":\"Couldn't find any profile with that name\"}"),
      @CommentValue("Sample CloudFlare API not exists response: {\"code\":404,\"error\":\"Not Found\",\"reason\":\"No user with the name 'someletters123456' was found\"}"),
      @CommentValue(),
      @CommentValue("Responses with an invalid scheme will be identified as responses with a server error"),
      @CommentValue("Set this parameter to [], to disable JSON scheme validation"),
  })
  public List<String> userExistsJsonValidatorFields = List.of("name", "id");
  public String jsonUuidField = "id";
  public List<String> userNotExistsJsonValidatorFields = List.of();

  @Comment({
      @CommentValue("If Mojang rate-limits your server, we cannot determine if the player is premium or not"),
      @CommentValue("This option allows you to choose whether every player will be defined as premium or as cracked while Mojang is rate-limiting the server"),
      @CommentValue("True - as premium; False - as cracked"),
  })
  public boolean onRateLimitPremium = true;

  @Comment({
      @CommentValue("If Mojang API is down, we cannot determine if the player is premium or not"),
      @CommentValue("This option allows you to choose whether every player will be defined as premium or as cracked while Mojang API is unavailable"),
      @CommentValue("True - as premium; False - as cracked"),
  })
  public boolean onServerErrorPremium = true;

  public List<String> registerCommand = List.of("/r", "/reg", "/register");
  public List<String> loginCommand = List.of("/l", "/log", "/login");
  public List<String> totpCommand = List.of("/2fa", "/totp");

  @Comment(@CommentValue("New players will be kicked with registrations-disabled-kick message"))
  public boolean disableRegistrations = false;

  public Mod mod = new Mod();

  @Comment({
      @CommentValue("Implement the automatic login using the plugin, the LimboAuth client mod and optionally using a custom launcher"),
      @CommentValue("See https://github.com/Elytrium/LimboAuth-ClientMod"),
  })
  public static class Mod {

    public boolean enabled = true;

    @Comment(@CommentValue("Should the plugin forbid logging in without a mod"))
    public boolean loginOnlyByMod = false;

    @Comment(@CommentValue("The key must be the same in the plugin config and in the server hash issuer, if you use it"))
    @Serializer(MD5KeySerializer.class)
    public byte[] verifyKey = null;

  }

  public WorldCoords worldCoords = new WorldCoords();

  public static class WorldCoords {

    public int posX = 0;
    public int posY = 0;
    public int posZ = 0;
  }

  public AuthCoords authCoords = new AuthCoords();

  public static class AuthCoords {

    public double posX = 0;
    public double posY = 0;
    public double posZ = 0;
    public double yaw = 0;
    public double pitch = 0;
  }

  public CrackedTitleSettings crackedTitleSettings = new CrackedTitleSettings();

  public static class CrackedTitleSettings {

    public int fadeIn = 10;
    public int stay = 70;
    public int fadeOut = 20;
    public boolean clearAfterLogin = false;

    public Title.Times toTimes() {
      return Title.Times.times(Ticks.duration(this.fadeIn), Ticks.duration(this.stay), Ticks.duration(this.fadeOut));
    }
  }

  public PremiumTitleSettings premiumTitleSettings = new PremiumTitleSettings();

  public static class PremiumTitleSettings {

    public int fadeIn = 10;
    public int stay = 70;
    public int fadeOut = 20;

    public Title.Times toTimes() {
      return Title.Times.times(Ticks.duration(this.fadeIn), Ticks.duration(this.stay), Ticks.duration(this.fadeOut));
    }
  }

  public CommandPermissionStateConfig commandPermissionState = new CommandPermissionStateConfig();

  @Comment({
      @CommentValue("Available values: FALSE, TRUE, PERMISSION"),
      @CommentValue(" FALSE - the command will be disallowed"),
      @CommentValue(" TRUE - the command will be allowed if player has false permission state"),
      @CommentValue(" PERMISSION - the command will be allowed if player has true permission state"),
  })
  public static class CommandPermissionStateConfig {

    @Comment(@CommentValue("Permission: limboauth.commands.changepassword"))
    public CommandPermissionState changePassword = CommandPermissionState.PERMISSION;
    @Comment(@CommentValue("Permission: limboauth.commands.destroysession"))
    public CommandPermissionState destroySession = CommandPermissionState.PERMISSION;
    @Comment(@CommentValue("Permission: limboauth.commands.premium"))
    public CommandPermissionState premium = CommandPermissionState.PERMISSION;
    @Comment(@CommentValue("Permission: limboauth.commands.totp"))
    public CommandPermissionState totp = CommandPermissionState.PERMISSION;
    @Comment(@CommentValue("Permission: limboauth.commands.unregister"))
    public CommandPermissionState unregister = CommandPermissionState.PERMISSION;

    @Comment(@CommentValue("Permission: limboauth.admin.forcechangepassword"))
    public CommandPermissionState forceChangePassword = CommandPermissionState.PERMISSION;
    @Comment(@CommentValue("Permission: limboauth.admin.forceregister"))
    public CommandPermissionState forceRegister = CommandPermissionState.PERMISSION;
    @Comment(@CommentValue("Permission: limboauth.admin.forceunregister"))
    public CommandPermissionState forceUnregister = CommandPermissionState.PERMISSION;
    @Comment(@CommentValue("Permission: limboauth.admin.reload"))
    public CommandPermissionState reload = CommandPermissionState.PERMISSION;
    @Comment(@CommentValue("Permission: limboauth.admin.help"))
    public CommandPermissionState help = CommandPermissionState.TRUE;
  }

  private static Component component(String value) {
    return Settings.IMP.serializer.getSerializer().deserialize(value);
  }

  public Messages messages = new Messages();

  public static class Messages {

    public Component reload = Settings.component("LimboAuth &6>>&f &aReloaded successfully!");
    public Component errorOccurred = Settings.component("LimboAuth &6>>&f &cAn internal error has occurred!");
    public Component databaseErrorKick = Settings.component("LimboAuth &6>>&f &cA database error has occurred!");

    public Component notPlayer = Settings.component("LimboAuth &6>>&f &cСonsole is not allowed to execute this command!");
    public Component notRegistered = Settings.component("LimboAuth &6>>&f &cYou are not registered or your account is &6PREMIUM!");
    public Component crackedCommand = Settings.component("LimboAuth &6>>&f\n&aYou can not use this command since your account is &6PREMIUM&a!");
    public Component wrongPassword = Settings.component("LimboAuth &6>>&f &cPassword is wrong!");

    public Component nicknameInvalidKick = Settings.component("LimboAuth &6>>&f\n&cYour nickname contains forbidden characters. Please, change your nickname!");
    public Component reconnectKick = Settings.component("LimboAuth &6>>&f\n&cReconnect to the server to verify your account!");

    @Comment(@CommentValue("6 hours by default in ip-limit-valid-time"))
    public Component ipLimitKick = Settings.component("LimboAuth &6>>&f\n\n&cYour IP has reached max registered accounts. If this is an error, restart your router, or wait about 6 hours.");
    @RegisterPlaceholders({"REQUIRED", "CURRENT"})
    public Component wrongNicknameCaseKick = Settings.component("LimboAuth &6>>&f\n&cYou should join using username &6{REQUIRED}&c, not &6{CURRENT}&c.");

    @RegisterPlaceholders("REMAINING")
    public BossBar bossbar = BossBar.bossBar(Settings.component("LimboAuth &6>>&f You have &6{REMAINING} &fseconds left to log in."), BossBar.MAX_PROGRESS, BossBar.Color.RED, BossBar.Overlay.NOTCHED_20);
    public Component timesUp = Settings.component("LimboAuth &6>>&f\n&cAuthorization time is up.");

    @Comment(value = @CommentValue("Can be empty."), at = Comment.At.SAME_LINE)
    public Component loginPremiumMessage = Settings.component("LimboAuth &6>>&f You've been logged in automatically using the premium account!");
    public Title loginPremiumTitle = Title.title(Settings.component("LimboAuth &6>>&f Welcome!"), Settings.component("&aYou has been logged in as premium player!"));
    @Comment(value = @CommentValue("Can be empty."), at = Comment.At.SAME_LINE)
    public Component loginFloodgate = Settings.component("LimboAuth &6>>&f You've been logged in automatically using the bedrock account!");
    public Title loginFloodgateTitle = Title.title(Settings.component("LimboAuth &6>>&f Welcome!"), Settings.component("&aYou has been logged in as bedrock player!"));

    @RegisterPlaceholders("ATTEMPTS")
    public Component loginMessage = Settings.component("LimboAuth &6>>&f &aPlease, login using &6/login <password>&a, you have &6{ATTEMPTS} &aattempts.");
    @RegisterPlaceholders("ATTEMPTS")
    public Component loginWrongPassword = Settings.component("LimboAuth &6>>&f &cYou''ve entered the wrong password, you have &6{ATTEMPTS} &cattempts left.");
    public Component loginWrongPasswordKick = Settings.component("LimboAuth &6>>&f\n&cYou've entered the wrong password numerous times!");
    public Component loginSuccessful = Settings.component("LimboAuth &6>>&f &aSuccessfully logged in!");
    @RegisterPlaceholders("ATTEMPTS")
    public Title loginTitle = Title.title(Settings.component("&fPlease, login using &6/login <password>&a."), Settings.component("&aYou have &6{ATTEMPTS} &aattempts."));
    @Comment(value = @CommentValue("Can be empty."), at = Comment.At.SAME_LINE)
    public Title loginSuccessfulTitle = Title.title(Settings.component("LimboAuth &6>>&f"), Settings.component("&aSuccessfully logged in!"));

    @Comment(@CommentValue("Or if register-need-repeat-password set to false remove the \"<repeat password>\" part."))
    public Component registerMessage = Settings.component("LimboAuth &6>>&f Please, register using &6/register <password> <repeat password>");
    public Component registerDifferentPasswords = Settings.component("LimboAuth &6>>&f &cThe entered passwords differ from each other!");
    public Component registerPasswordTooShort = Settings.component("LimboAuth &6>>&f &cYou entered too short password, use a different one!");
    public Component registerPasswordTooLong = Settings.component("LimboAuth &6>>&f &cYou entered too long password, use a different one!");
    public Component registerPasswordUnsafe = Settings.component("LimboAuth &6>>&f &cYour password is unsafe, use a different one!");
    public Component registerSuccessful = Settings.component("LimboAuth &6>>&f &aSuccessfully registered!");
    public Title registerTitle = Title.title(Settings.component("LimboAuth &6>>&f"), Settings.component("&aPlease, register using &6/register <password> <repeat password>"));
    public Title registerSuccessfulTitle = Title.title(Settings.component("LimboAuth &6>>&f"), Settings.component("&aSuccessfully registered!"));

    public Component unregisterSuccessful = Settings.component("LimboAuth &6>>&f\n&aSuccessfully unregistered!");
    public Component unregisterUsage = Settings.component("LimboAuth &6>>&f Usage: &6/unregister <current password> confirm");

    public Component premiumSuccessful = Settings.component("LimboAuth &6>>&f\n&aSuccessfully changed account state to &6PREMIUM&a!");
    public Component alreadyPremium = Settings.component("LimboAuth &6>>&f &cYour account is already &6PREMIUM&c!");
    public Component notPremium = Settings.component("LimboAuth &6>>&f &cYour account is not &6PREMIUM&c!");
    public Component premiumUsage = Settings.component("LimboAuth &6>>&f Usage: &6/premium <current password> confirm");

    public Component pairSuccessful = Settings.component("LimboAuth &6>>&f\n&aNow you can login only with &6LimboAuth Client Mod&a!");
    public Component unpairSuccessful = Settings.component("LimboAuth &6>>&f\n&aNow you can login without &6LimboAuth Client Mod&a!");
    public Component alreadyPaired = Settings.component("LimboAuth &6>>&f &cYour account is already &6PAIRED&c!");
    public Component notPaired = Settings.component("LimboAuth &6>>&f &cYour account is not &6PAIRED&c!");
    public Component modNotFound = Settings.component("LimboAuth &6>>&f &cYou can't pair account without LimboAuth Client Mod installed!");
    public Component pairUsage = Settings.component("LimboAuth &6>>&f Usage: &6/pair <current password> confirm");
    public Component unpairUsage = Settings.component("LimboAuth &6>>&f Usage: &6/unpair <current password> confirm");

    public Component eventCancelled = Settings.component("LimboAuth &6>>&f Authorization event was cancelled");

    @RegisterPlaceholders("TARGET")
    public Component forceUnregisterSuccessful = Settings.component("LimboAuth &6>>&f &6{TARGET} &asuccessfully unregistered!");
    public Component forceUnregisterKick = Settings.component("LimboAuth &6>>&f\n&aYou have been unregistered by administrator!");
    @RegisterPlaceholders("TARGET")
    public Component forceUnregisterNotSuccessful = Settings.component("LimboAuth &6>>&f &cUnable to unregister &6{TARGET}&c. Most likely this player has never been on this server.");
    public Component forceUnregisterUsage = Settings.component("LimboAuth &6>>&f Usage: &6/forceunregister <nickname>");

    public Component registrationsDisabledKick = Settings.component("LimboAuth &6>>&f Registrations are currently disabled.");

    public Component changePasswordSuccessful = Settings.component("LimboAuth &6>>&f &aSuccessfully changed password!");
    @Comment(@CommentValue("Or if change-password-need-old-pass set to false remove the \"<old password>\" part."))
    public Component changePasswordUsage = Settings.component("LimboAuth &6>>&f Usage: &6/changepassword <old password> <new password>");

    @RegisterPlaceholders("TARGET")
    public Component forceChangePasswordSuccessful = Settings.component("LimboAuth &6>>&f &aSuccessfully changed password for player &6{TARGET}&a!");
    @RegisterPlaceholders("PASSWORD")
    public Component forceChangePasswordMessage = Settings.component("LimboAuth &6>>&f &aYour password has been changed to &6{PASSWORD} &aby administator!");
    @RegisterPlaceholders("TARGET")
    public Component forceChangePasswordNotSuccessful = Settings.component("LimboAuth &6>>&f &cUnable to change password for &6{TARGET}&c. Most likely this player has never been on this server.");
    @RegisterPlaceholders("TARGET")
    public Component forceChangePasswordNotRegistered = Settings.component("LimboAuth &6>>&f &cPlayer &6{TARGET}&c is not registered.");
    public Component forceChangePasswordUsage = Settings.component("LimboAuth &6>>&f Usage: &6/forcechangepassword <nickname> <new password>");

    public Component forceRegisterUsage = Settings.component("LimboAuth &6>>&f Usage: &6/forceregister <nickname> <password>");
    public Component forceRegisterIncorrectNickname = Settings.component("LimboAuth &6>>&f &cNickname contains forbidden characters.");
    public Component forceRegisterTakenNickname = Settings.component("LimboAuth &6>>&f &cThis nickname is already taken.");
    @RegisterPlaceholders("TARGET")
    public Component forceRegisterSuccessful = Settings.component("LimboAuth &6>>&f &aSuccessfully registered player &6{TARGET}&a!");
    @RegisterPlaceholders("TARGET")
    public Component forceRegisterNotSuccessful = Settings.component("LimboAuth &6>>&f &cUnable to register player &6{TARGET}&c.");

    public Component totpMessage = Settings.component("LimboAuth &6>>&f Please, enter your 2FA key using &6/2fa <key>");
    public Title totpTitle = Title.title(Settings.component("LimboAuth &6>>&f"), Settings.component("&aEnter your 2FA key using &6/2fa <key>"));
    public Component totpSuccessful = Settings.component("LimboAuth &6>>&f &aSuccessfully enabled 2FA!");
    public Component totpDisabled = Settings.component("LimboAuth &6>>&f &aSuccessfully disabled 2FA!");
    @Comment(@CommentValue("Or if totp-need-pass set to false remove the \"<current password>\" part."))
    public Component totpUsage = Settings.component("LimboAuth &6>>&f Usage: &6/2fa enable <current password>&f or &6/2fa disable <totp key>&f.");
    public Component totpWrong = Settings.component("LimboAuth &6>>&f &cWrong 2FA key!");
    public Component totpAlreadyEnabled = Settings.component("LimboAuth &6>>&f &c2FA is already enabled. Disable it using &6/2fa disable <key>&c.");
    public Component totpQr = Settings.component("LimboAuth &6>>&f Click here to open 2FA QR code in browser.");
    @RegisterPlaceholders("TOKEN")
    public Component totpToken = Settings.component("LimboAuth &6>>&f &aYour 2FA token &7(Click to copy)&a: &6{TOKEN}");
    @RegisterPlaceholders("CODES")
    public Component totpRecovery = Settings.component("LimboAuth &6>>&f &aYour recovery codes &7(Click to copy)&a: &6{CODES}");

    public Component destroySessionSuccessful = Settings.component("LimboAuth &6>>&f &eYour session is now destroyed, you'll need to log in again after reconnecting.");

    public Component modSessionExpired = Settings.component("LimboAuth &6>>&f Your session has expired, log in again.");
  }

  public Database database = new Database();

  @Comment(@CommentValue("Database settings"))
  public static class Database {

    @Comment(@CommentValue("Available database types: MariaDB, MySQL, PostgreSQL, SQLite or H2."))
    public DatabaseLibrary storageType = DatabaseLibrary.H2;

    @Comment(@CommentValue("Settings for Network-based database (like MySQL, PostgreSQL): "))
    public String hostname = "127.0.0.1:3306";
    public String user = "user";
    public String password = "password";
    public String database = "limboauth";
    public Map<String, String> connectionParameters = Map.of(
        "autoReconnect", "true",
        "initialTimeout", "1",
        "cachePrepStmts", "true",
        "prepStmtCacheSize", "250",
        "prepStmtCacheSqlLimit", "2048",
        "useServerPrepStmts", "true",
        "useLocalSessionState", "true",
        "cacheResultSetMetadata", "true",
        "cacheServerConfiguration", "true",
        "cacheCallableStmts", "true"
    );

    public String nicknameField = "NICKNAME";
    public String lowercaseNicknameField = "LOWERCASENICKNAME";
    public String hashField = "HASH";
    public String ipField = "IP";
    public String loginIpField = "LOGINIP";
    public String totpTokenField = "TOTPTOKEN";
    public String regDateField = "REGDATE";
    public String loginDateField = "LOGINDATE";
    public String uuidField = "UUID";
    public String premiumUuidField = "PREMIUMUUID";
    public String tokenIssuedAtField = "ISSUEDTIME";
    public String onlyByModField = "ONLYMOD";
  }

  public static class MD5KeySerializer extends ClassSerializer<byte[], String> {

    private final MessageDigest md5;
    private final Random random;

    private String originalValue;

    public MD5KeySerializer() throws NoSuchAlgorithmException {
      super(byte[].class, String.class);
      this.md5 = MessageDigest.getInstance("MD5");
      this.random = new SecureRandom();
    }

    @Override
    public String serialize(byte[] from) {
      if (this.originalValue == null || this.originalValue.isEmpty()) {
        String characters = "AaBbCcDdEeFfGgHhIiJjKkLlMmNnOoPpQqRrSsTtUuVvWwXxYyZz1234567890";
        StringBuilder result = new StringBuilder();
        for (int i = 23; i >= 0; --i) {
          result.append(characters.charAt(this.random.nextInt(characters.length())));
        }

        this.originalValue = result.toString();
      }

      return this.originalValue;
    }

    @Override
    public byte[] deserialize(String from) {
      this.originalValue = from;
      return this.md5.digest(from.getBytes(StandardCharsets.UTF_8));
    }
  }
}
