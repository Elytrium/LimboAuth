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

import java.net.URLDecoder;
import java.net.URLEncoder;
import java.nio.charset.StandardCharsets;
import java.util.concurrent.ThreadLocalRandom;
import net.elytrium.fastutil.ints.IntArrayList;
import net.elytrium.fastutil.objects.Object2ObjectLinkedOpenHashMap;
import net.elytrium.fastutil.objects.ObjectArrayList;
import net.elytrium.limboapi.api.chunk.Dimension;
import net.elytrium.limboapi.api.file.BuiltInWorldFileType;
import net.elytrium.limboapi.api.player.GameMode;
import net.elytrium.limboauth.command.PermissionState;
import net.elytrium.limboauth.data.DataSourceType;
import net.elytrium.limboauth.data.PlayerData;
import net.elytrium.limboauth.migration.MigrationHash;
import net.elytrium.limboauth.serialization.replacers.ComponentReplacer;
import net.elytrium.limboauth.serialization.replacers.TitleReplacer;
import net.elytrium.limboauth.serialization.serializers.BossBarSerializer;
import net.elytrium.limboauth.serialization.serializers.ComponentSerializer;
import net.elytrium.limboauth.serialization.serializers.TitleSerializer;
import net.elytrium.limboauth.utils.Hashing;
import net.elytrium.limboauth.utils.Maps;
import net.elytrium.serializer.SerializerConfig;
import net.elytrium.serializer.annotations.Comment;
import net.elytrium.serializer.annotations.CommentValue;
import net.elytrium.serializer.annotations.NewLine;
import net.elytrium.serializer.annotations.RegisterPlaceholders;
import net.elytrium.serializer.annotations.Serializer;
import net.elytrium.serializer.custom.ClassSerializer;
import net.elytrium.serializer.language.object.YamlSerializable;
import net.kyori.adventure.bossbar.BossBar;
import net.kyori.adventure.text.Component;
import net.kyori.adventure.title.Title;
import org.bouncycastle.crypto.params.KeyParameter;

public class Settings extends YamlSerializable {

  public static final Settings HEAD = new Settings(new SerializerConfig.Builder()
      .registerSerializer(ComponentSerializer.INSTANCE)
      .registerSerializer(TitleSerializer.INSTANCE)
      .registerSerializer(BossBarSerializer.INSTANCE)
      .registerReplacer(ComponentReplacer.INSTANCE)
      .registerReplacer(TitleReplacer.INSTANCE)
      .setCommentValueIndent(1)
      .build()
  );
  public static final net.elytrium.limboauth.serialization.ComponentSerializer SERIALIZER = Settings.HEAD.serializer;
  public static final PermissionStates PERMISSION_STATES = Settings.HEAD.permissionStates;
  public static final Settings.Messages MESSAGES = Settings.HEAD.messages;
  public static final Settings.Database DATABASE = Settings.HEAD.database;

  private Settings(SerializerConfig config) {
    super(config);
  }

  public final String version = BuildConfig.VERSION;

  @Comment({
      @CommentValue("Available serializers:"),
      @CommentValue("LEGACY_AMPERSAND - \"&c&lExample &c&9Text\"."),
      @CommentValue("LEGACY_SECTION - \"§c§lExample §c§9Text\"."),
      @CommentValue("MINIMESSAGE - \"<bold><red>Example</red> <blue>Text</blue></bold>\". (https://webui.adventure.kyori.net/)"),
      @CommentValue("GSON - \"[{\"text\":\"Example\",\"bold\":true,\"color\":\"red\"},{\"text\":\" \",\"bold\":true},{\"text\":\"Text\",\"bold\":true,\"color\":\"blue\"}]\". (https://minecraft.tools/en/json_text.php/)"),
  })
  net.elytrium.limboauth.serialization.ComponentSerializer serializer = net.elytrium.limboauth.serialization.ComponentSerializer.LEGACY_AMPERSAND;

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
      @CommentValue("All hashes ignores first \"$\" if present."),
      @CommentValue(type = CommentValue.Type.NEW_LINE),
      @CommentValue("SHA256_NO_SALT  - SHA256(        password        ) that looks like SHA$hash      (NexAuth)"),
      @CommentValue("SHA256_MOONAUTH - SHA256(    SHA256(password)    ) that looks like SHA$hash"),
      @CommentValue("SHA256          - SHA256(     password     + salt) that looks like SHA$salt$hash"),
      @CommentValue("SHA256_AUTHME   - SHA256( SHA256(password) + salt) that looks like SHA$salt$hash (AuthMe, MoonVKAuth, DSKAuth, DBA, JPremium)"),
      @CommentValue(type = CommentValue.Type.NEW_LINE),
      @CommentValue("SHA512_NO_SALT  - SHA512(        password        ) that looks like SHA$hash      (NexAuth)"),
      @CommentValue("SHA512          - SHA512(     password     + salt) that looks like SHA$salt$hash"),
      @CommentValue("SHA512_DBA      - SHA512( SHA512(password) + salt) that looks like SHA$salt$hash (DBA, JPremium)"),
      @CommentValue("SHA512_REVERSED - SHA512(     password     + salt) that looks like SHA$hash$salt (nLogin)"),
      @CommentValue("SHA512_NLOGIN   - SHA512( SHA512(password) + salt) that looks like SHA$hash$salt (nLogin)"),
      @CommentValue(type = CommentValue.Type.NEW_LINE),
      @CommentValue("ARGON2          - Hash that looks like argon2i$v=19$m=1234,t=1234,p=1234$salt$hash (All argon2 versions are supported: argon2d, argon2i, argon2id)"),
      @CommentValue("MD5             - Hash that looks like 0b28572ad58a2662c77825de2b39c00d"), // Jokerge
      @CommentValue("PLAINTEXT       - Plain text"),
  })
  public MigrationHash migrationHash = null;
  @Comment(@CommentValue("Available dimensions: OVERWORLD, NETHER, THE_END"))
  public Dimension dimension = Dimension.THE_END;
  public long purgeCacheMillis = 3600000;
  public long purgePremiumCacheMillis = 28800000;
  public long purgeBruteforceCacheMillis = 28800000;
  @Comment(@CommentValue("Used to ban IPs when a possible attacker incorrectly enters the password"))
  public int bruteforceMaxAttempts = 10;
  @Comment(@CommentValue("QR Generator URL, set {data} placeholder"))
  @RegisterPlaceholders("DATA")
  public String qrGeneratorUrl = "https://chart.googleapis.com/chart?cht=qr&chs=200x200&chl={DATA}&chld=M|1";
  @Serializer(URLEncoderSerializer.class)
  public String totpIssuer = "LimboAuth by Elytrium";
  @Comment(@CommentValue("Should be in range [4, 31]"))
  public int bcryptCost = 10;
  @Comment(@CommentValue("Available versions: 2, 2a, 2b, 2x, 2y"))
  public String bcryptVersion = "2a";
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
  public long worldTicks = 1000;

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
  public IntArrayList statusCodeUserExists = IntArrayList.of(200);
  @Comment(@CommentValue("Set 204 and 404 if you use Mojang API, 404 if you use CloudFlare API"))
  public IntArrayList statusCodeUserNotExists = IntArrayList.of(204, 404);
  @Comment(@CommentValue("Set 429 if you use Mojang or CloudFlare API"))
  public IntArrayList statusCodeRateLimit = IntArrayList.of(429);

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
  public ObjectArrayList<String> userExistsJsonValidatorFields = ObjectArrayList.of("name", "id");
  public String jsonUuidField = "id";
  public ObjectArrayList<String> userNotExistsJsonValidatorFields = ObjectArrayList.of();

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

  public ObjectArrayList<String> registerCommand = ObjectArrayList.of("/r", "/reg", "/register");
  public ObjectArrayList<String> loginCommand = ObjectArrayList.of("/l", "/log", "/login");
  public ObjectArrayList<String> totpCommand = ObjectArrayList.of("/2fa", "/totp");

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
    public KeyParameter verifyKey = null;
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

  @Comment(@CommentValue("Affects only cracked users"))
  public boolean clearTitleAfterLogin = false;

  PermissionStates permissionStates = new PermissionStates();

  @Comment({
      @CommentValue("Available values: FALSE, TRUE, PERMISSION"),
      @CommentValue(" FALSE - the command will be disallowed"),
      @CommentValue(" TRUE - the command will be allowed if player permission state is set to true or not set altogether"),
      @CommentValue(" PERMISSION - the command will be allowed only if player have true permission state"),
  })
  public static class PermissionStates {

    @Comment(@CommentValue("Permission: limboauth.commands.changepassword"))
    public PermissionState changePassword = PermissionState.PERMISSION;
    @Comment(@CommentValue("Permission: limboauth.commands.destroysession"))
    public PermissionState destroySession = PermissionState.PERMISSION;
    @Comment(@CommentValue("Permission: limboauth.commands.premium"))
    public PermissionState premium = PermissionState.PERMISSION;
    @Comment(@CommentValue("Permission: limboauth.commands.totp"))
    public PermissionState totp = PermissionState.PERMISSION;
    @Comment(@CommentValue("Permission: limboauth.commands.unregister"))
    public PermissionState unregister = PermissionState.PERMISSION;

    @Comment(@CommentValue("Permission: limboauth.admin.forcechangepassword"))
    public PermissionState forceChangePassword = PermissionState.PERMISSION;
    @Comment(@CommentValue("Permission: limboauth.admin.forceregister"))
    public PermissionState forceRegister = PermissionState.PERMISSION;
    @Comment(@CommentValue("Permission: limboauth.admin.forceunregister"))
    public PermissionState forceUnregister = PermissionState.PERMISSION;
    @Comment(@CommentValue("Permission: limboauth.admin.reload"))
    public PermissionState reload = PermissionState.PERMISSION;
    @Comment(@CommentValue("Permission: limboauth.admin.help"))
    public PermissionState help = PermissionState.TRUE;
  }

  Messages messages = new Messages();

  public static class Messages {

    public Component reload = Settings.SERIALIZER.deserialize("LimboAuth &6>>&f &aReloaded successfully!");
    public Component errorOccurred = Settings.SERIALIZER.deserialize("LimboAuth &6>>&f &cAn internal error has occurred!");
    public Component databaseErrorKick = Settings.SERIALIZER.deserialize("LimboAuth &6>>&f &cA database error has occurred!");

    public Component notPlayer = Settings.SERIALIZER.deserialize("LimboAuth &6>>&f &cСonsole is not allowed to execute this command!");
    public Component notRegistered = Settings.SERIALIZER.deserialize("LimboAuth &6>>&f &cYou are not registered or your account is &6PREMIUM!");
    public Component crackedCommand = Settings.SERIALIZER.deserialize("LimboAuth &6>>&f\n&aYou can not use this command since your account is &6PREMIUM&a!");
    public Component wrongPassword = Settings.SERIALIZER.deserialize("LimboAuth &6>>&f &cPassword is wrong!");

    public Component nicknameInvalidKick = Settings.SERIALIZER.deserialize("LimboAuth &6>>&f\n&cYour nickname contains forbidden characters. Please, change your nickname!");
    public Component reconnectKick = Settings.SERIALIZER.deserialize("LimboAuth &6>>&f\n&cReconnect to the server to verify your account!");

    @Comment(@CommentValue("6 hours by default in ip-limit-valid-time"))
    public Component ipLimitKick = Settings.SERIALIZER.deserialize("LimboAuth &6>>&f\n\n&cYour IP has reached max registered accounts. If this is an error, restart your router, or wait about 6 hours.");
    @RegisterPlaceholders({"REQUIRED", "CURRENT"})
    public Component wrongNicknameCaseKick = Settings.SERIALIZER.deserialize("LimboAuth &6>>&f\n&cYou should join using username &6{REQUIRED}&c, not &6{CURRENT}&c.");

    @RegisterPlaceholders("REMAINING")
    public BossBar bossbar = BossBar.bossBar(Settings.SERIALIZER.deserialize("LimboAuth &6>>&f You have &6{REMAINING} &fseconds left to log in."), BossBar.MAX_PROGRESS, BossBar.Color.RED, BossBar.Overlay.NOTCHED_20);
    public Component timesUp = Settings.SERIALIZER.deserialize("LimboAuth &6>>&f\n&cAuthorization time is up.");

    @Comment(value = @CommentValue("Can be empty."), at = Comment.At.SAME_LINE)
    public Component loginPremiumMessage = Settings.SERIALIZER.deserialize("LimboAuth &6>>&f You've been logged in automatically using the premium account!");
    public Title loginPremiumTitle = Title.title(Settings.SERIALIZER.deserialize("LimboAuth &6>>&f Welcome!"), Settings.SERIALIZER.deserialize("&aYou has been logged in as premium player!"));
    @Comment(value = @CommentValue("Can be empty."), at = Comment.At.SAME_LINE)
    public Component loginFloodgate = Settings.SERIALIZER.deserialize("LimboAuth &6>>&f You've been logged in automatically using the bedrock account!");
    public Title loginFloodgateTitle = Title.title(Settings.SERIALIZER.deserialize("LimboAuth &6>>&f Welcome!"), Settings.SERIALIZER.deserialize("&aYou has been logged in as bedrock player!"));

    @RegisterPlaceholders("ATTEMPTS")
    public Component loginMessage = Settings.SERIALIZER.deserialize("LimboAuth &6>>&f &aPlease, login using &6/login <password>&a, you have &6{ATTEMPTS} &aattempts.");
    @RegisterPlaceholders("ATTEMPTS")
    public Component loginWrongPassword = Settings.SERIALIZER.deserialize("LimboAuth &6>>&f &cYou''ve entered the wrong password, you have &6{ATTEMPTS} &cattempts left.");
    public Component loginWrongPasswordKick = Settings.SERIALIZER.deserialize("LimboAuth &6>>&f\n&cYou've entered the wrong password numerous times!");
    public Component loginSuccessful = Settings.SERIALIZER.deserialize("LimboAuth &6>>&f &aSuccessfully logged in!");
    @RegisterPlaceholders("ATTEMPTS")
    public Title loginTitle = Title.title(Settings.SERIALIZER.deserialize("&fPlease, login using &6/login <password>&a."), Settings.SERIALIZER.deserialize("&aYou have &6{ATTEMPTS} &aattempts."));
    @Comment(value = @CommentValue("Can be empty."), at = Comment.At.SAME_LINE)
    public Title loginSuccessfulTitle = Title.title(Settings.SERIALIZER.deserialize("LimboAuth &6>>&f"), Settings.SERIALIZER.deserialize("&aSuccessfully logged in!"));

    @Comment(@CommentValue("Or if register-need-repeat-password set to false remove the \"<repeat password>\" part."))
    public Component registerMessage = Settings.SERIALIZER.deserialize("LimboAuth &6>>&f Please, register using &6/register <password> <repeat password>");
    public Component registerDifferentPasswords = Settings.SERIALIZER.deserialize("LimboAuth &6>>&f &cThe entered passwords differ from each other!");
    public Component registerPasswordTooShort = Settings.SERIALIZER.deserialize("LimboAuth &6>>&f &cYou entered too short password, use a different one!");
    public Component registerPasswordTooLong = Settings.SERIALIZER.deserialize("LimboAuth &6>>&f &cYou entered too long password, use a different one!");
    public Component registerPasswordUnsafe = Settings.SERIALIZER.deserialize("LimboAuth &6>>&f &cYour password is unsafe, use a different one!");
    public Component registerSuccessful = Settings.SERIALIZER.deserialize("LimboAuth &6>>&f &aSuccessfully registered!");
    public Title registerTitle = Title.title(Settings.SERIALIZER.deserialize("LimboAuth &6>>&f"), Settings.SERIALIZER.deserialize("&aPlease, register using &6/register <password> <repeat password>"));
    public Title registerSuccessfulTitle = Title.title(Settings.SERIALIZER.deserialize("LimboAuth &6>>&f"), Settings.SERIALIZER.deserialize("&aSuccessfully registered!"));

    public Component unregisterSuccessful = Settings.SERIALIZER.deserialize("LimboAuth &6>>&f\n&aSuccessfully unregistered!");
    public Component unregisterUsage = Settings.SERIALIZER.deserialize("LimboAuth &6>>&f Usage: &6/unregister <current password> confirm");

    public Component premiumSuccessful = Settings.SERIALIZER.deserialize("LimboAuth &6>>&f\n&aSuccessfully changed account state to &6PREMIUM&a!");
    public Component alreadyPremium = Settings.SERIALIZER.deserialize("LimboAuth &6>>&f &cYour account is already &6PREMIUM&c!");
    public Component notPremium = Settings.SERIALIZER.deserialize("LimboAuth &6>>&f &cYour account is not &6PREMIUM&c!");
    public Component premiumUsage = Settings.SERIALIZER.deserialize("LimboAuth &6>>&f Usage: &6/premium <current password> confirm");

    public Component pairSuccessful = Settings.SERIALIZER.deserialize("LimboAuth &6>>&f\n&aNow you can login only with &6LimboAuth Client Mod&a!");
    public Component unpairSuccessful = Settings.SERIALIZER.deserialize("LimboAuth &6>>&f\n&aNow you can login without &6LimboAuth Client Mod&a!");
    public Component alreadyPaired = Settings.SERIALIZER.deserialize("LimboAuth &6>>&f &cYour account is already &6PAIRED&c!");
    public Component notPaired = Settings.SERIALIZER.deserialize("LimboAuth &6>>&f &cYour account is not &6PAIRED&c!");
    public Component modNotFound = Settings.SERIALIZER.deserialize("LimboAuth &6>>&f &cYou can't pair account without LimboAuth Client Mod installed!");
    public Component pairUsage = Settings.SERIALIZER.deserialize("LimboAuth &6>>&f Usage: &6/pair <current password> confirm");
    public Component unpairUsage = Settings.SERIALIZER.deserialize("LimboAuth &6>>&f Usage: &6/unpair <current password> confirm");

    public Component eventCancelled = Settings.SERIALIZER.deserialize("LimboAuth &6>>&f Authorization event was cancelled");

    @RegisterPlaceholders("TARGET")
    public Component forceUnregisterSuccessful = Settings.SERIALIZER.deserialize("LimboAuth &6>>&f &6{TARGET} &asuccessfully unregistered!");
    public Component forceUnregisterKick = Settings.SERIALIZER.deserialize("LimboAuth &6>>&f\n&aYou have been unregistered by administrator!");
    @RegisterPlaceholders("TARGET")
    public Component forceUnregisterNotSuccessful = Settings.SERIALIZER.deserialize("LimboAuth &6>>&f &cUnable to unregister &6{TARGET}&c. Most likely this player has never been on this server.");
    public Component forceUnregisterUsage = Settings.SERIALIZER.deserialize("LimboAuth &6>>&f Usage: &6/forceunregister <nickname>");

    public Component registrationsDisabledKick = Settings.SERIALIZER.deserialize("LimboAuth &6>>&f Registrations are currently disabled.");

    public Component changePasswordSuccessful = Settings.SERIALIZER.deserialize("LimboAuth &6>>&f &aSuccessfully changed password!");
    @Comment(@CommentValue("Or if change-password-need-old-pass set to false remove the \"<old password>\" part."))
    public Component changePasswordUsage = Settings.SERIALIZER.deserialize("LimboAuth &6>>&f Usage: &6/changepassword <old password> <new password>");

    @RegisterPlaceholders("TARGET")
    public Component forceChangePasswordSuccessful = Settings.SERIALIZER.deserialize("LimboAuth &6>>&f &aSuccessfully changed password for player &6{TARGET}&a!");
    @RegisterPlaceholders("PASSWORD")
    public Component forceChangePasswordMessage = Settings.SERIALIZER.deserialize("LimboAuth &6>>&f &aYour password has been changed to &6{PASSWORD} &aby administator!");
    @RegisterPlaceholders("TARGET")
    public Component forceChangePasswordNotSuccessful = Settings.SERIALIZER.deserialize("LimboAuth &6>>&f &cUnable to change password for &6{TARGET}&c. Most likely this player has never been on this server.");
    @RegisterPlaceholders("TARGET")
    public Component forceChangePasswordNotRegistered = Settings.SERIALIZER.deserialize("LimboAuth &6>>&f &cPlayer &6{TARGET}&c is not registered.");
    public Component forceChangePasswordUsage = Settings.SERIALIZER.deserialize("LimboAuth &6>>&f Usage: &6/forcechangepassword <nickname> <new password>");

    public Component forceRegisterUsage = Settings.SERIALIZER.deserialize("LimboAuth &6>>&f Usage: &6/forceregister <nickname> <password>");
    public Component forceRegisterIncorrectNickname = Settings.SERIALIZER.deserialize("LimboAuth &6>>&f &cNickname contains forbidden characters.");
    public Component forceRegisterTakenNickname = Settings.SERIALIZER.deserialize("LimboAuth &6>>&f &cThis nickname is already taken.");
    @RegisterPlaceholders("TARGET")
    public Component forceRegisterSuccessful = Settings.SERIALIZER.deserialize("LimboAuth &6>>&f &aSuccessfully registered player &6{TARGET}&a!");
    @RegisterPlaceholders("TARGET")
    public Component forceRegisterNotSuccessful = Settings.SERIALIZER.deserialize("LimboAuth &6>>&f &cUnable to register player &6{TARGET}&c.");

    public Component totpMessage = Settings.SERIALIZER.deserialize("LimboAuth &6>>&f Please, enter your 2FA key using &6/2fa <key>");
    public Title totpTitle = Title.title(Settings.SERIALIZER.deserialize("LimboAuth &6>>&f"), Settings.SERIALIZER.deserialize("&aEnter your 2FA key using &6/2fa <key>"));
    public Component totpSuccessful = Settings.SERIALIZER.deserialize("LimboAuth &6>>&f &aSuccessfully enabled 2FA!");
    public Component totpDisabled = Settings.SERIALIZER.deserialize("LimboAuth &6>>&f &aSuccessfully disabled 2FA!");
    @Comment(@CommentValue("Or if totp-need-pass set to false remove the \"<current password>\" part."))
    public Component totpUsage = Settings.SERIALIZER.deserialize("LimboAuth &6>>&f Usage: &6/2fa enable <current password>&f or &6/2fa disable <totp key>&f.");
    public Component totpWrong = Settings.SERIALIZER.deserialize("LimboAuth &6>>&f &cWrong 2FA key!");
    public Component totpAlreadyEnabled = Settings.SERIALIZER.deserialize("LimboAuth &6>>&f &c2FA is already enabled. Disable it using &6/2fa disable <key>&c.");
    public Component totpQr = Settings.SERIALIZER.deserialize("LimboAuth &6>>&f Click here to open 2FA QR code in browser.");
    @RegisterPlaceholders("TOKEN")
    public Component totpToken = Settings.SERIALIZER.deserialize("LimboAuth &6>>&f &aYour 2FA token &7(Click to copy)&a: &6{TOKEN}");
    @RegisterPlaceholders("CODES")
    public Component totpRecovery = Settings.SERIALIZER.deserialize("LimboAuth &6>>&f &aYour recovery codes &7(Click to copy)&a: &6{CODES}");

    public Component destroySessionSuccessful = Settings.SERIALIZER.deserialize("LimboAuth &6>>&f &eYour session is now destroyed, you'll need to log in again after reconnecting.");

    public Component modSessionExpired = Settings.SERIALIZER.deserialize("LimboAuth &6>>&f Your session has expired, log in again.");
  }

  Database database = new Database();

  @Comment(@CommentValue("Database settings"))
  public static class Database {

    @Comment(@CommentValue("Available database types: MariaDB, MySQL, PostgreSQL, SQLite or H2."))
    public DataSourceType storageType = DataSourceType.H2;

    @Comment(@CommentValue("Settings for Network-based database (like MySQL, PostgreSQL): "))
    public String hostname = "127.0.0.1:3306";
    public String username = "user";
    public String password = "password";
    public String database = "limboauth";

    @NewLine
    public int connectionTimeout = 5000;
    public int maxLifetime = 1800000;
    public int maximumPoolSize = 10;
    public int minimumIdle = 10;
    public int keepaliveTime = 0;

    @NewLine
    @Comment(value = {
        @CommentValue("useSSL: \"false\""),
        @CommentValue("verifyServerCertificate: \"false\"")
    }, at = Comment.At.APPEND)
    public Object2ObjectLinkedOpenHashMap<String, String> connectionParameters = Maps.o2o(
        "useUnicode", "true",
        "characterEncoding", "utf8",
        "cachePrepStmts", "true",
        "prepStmtCacheSize", "250",
        "prepStmtCacheSqlLimit", "2048",
        "useServerPrepStmts", "true",
        "useLocalSessionState", "true",
        "rewriteBatchedStatements", "true",
        "cacheResultSetMetadata", "true",
        "cacheServerConfiguration", "true",
        "elideSetAutoCommits", "true",
        "maintainTimeStats", "false",
        "alwaysSendSetIsolation", "false",
        "cacheCallableStmts", "true",
        "serverTimezone", "UTC",
        "socketTimeout", "30000"
    );

    public PlayerData.Table table = new PlayerData.Table();

    public static class Table {

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
  }

  private static class MD5KeySerializer extends ClassSerializer<KeyParameter, String> {

    private String originalValue; // TODO map

    @Override
    public String serialize(KeyParameter from) {
      if (this.originalValue == null || this.originalValue.isEmpty()) {
        // TODO better generator
        String characters = "AaBbCcDdEeFfGgHhIiJjKkLlMmNnOoPpQqRrSsTtUuVvWwXxYyZz1234567890";
        StringBuilder result = new StringBuilder();
        for (int i = 23; i >= 0; --i) {
          result.append(characters.charAt(ThreadLocalRandom.current().nextInt(characters.length())));
        }

        this.originalValue = result.toString();
      }

      return this.originalValue;
    }

    @Override
    public KeyParameter deserialize(String from) {
      this.originalValue = from;
      return new KeyParameter(Hashing.md5(from));
    }
  }

  private static class URLEncoderSerializer extends ClassSerializer<String, String> {

    @Override
    public String serialize(String from) {
      return URLDecoder.decode(from, StandardCharsets.UTF_8);
    }

    @Override
    public String deserialize(String from) {
      return URLEncoder.encode(from, StandardCharsets.UTF_8);
    }
  }
}
