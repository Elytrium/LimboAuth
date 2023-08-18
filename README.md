# LimboAuth for IPS4

Pairing authorization minecraft server on velocity with IPB4

## See also

- [LimboFilter](https://github.com/Elytrium/LimboFilter) - Most powerful bot filtering solution for Minecraft proxies. Built with [LimboAPI](https://github.com/Elytrium/LimboAPI).
- [LimboAPI](https://github.com/Elytrium/LimboAPI) - Library for sending players to virtual servers (called limbo)

## Features of LimboAuth

- Supports [H2](https://www.h2database.com/html/main.html), [MySQL](https://www.mysql.com/about/), [PostgreSQL](https://www.postgresql.org/about/) [databases](https://en.wikipedia.org/wiki/Database);
- [Geyser](https://wiki.geysermc.org) [Floodgate](https://wiki.geysermc.org/floodgate/) support;
- Hybrid ([Floodgate](https://wiki.geysermc.org/floodgate/)/Online/Offline) mode support;
- Uses [BCrypt](https://en.wikipedia.org/wiki/Bcrypt) - the best [hashing algorithm](https://en.wikipedia.org/wiki/Cryptographic_hash_function) for password;
- Ability to migrate from [AuthMe](https://www.spigotmc.org/resources/authmereloaded.6269/)-alike plugins;
- Ability to block weak passwords;
- [TOTP](https://en.wikipedia.org/wiki/Time-based_one-time_password) [2FA](https://en.wikipedia.org/wiki/Help:Two-factor_authentication) support;
- Ability to set [UUID](https://minecraft.fandom.com/wiki/Universally_unique_identifier) from [database](https://en.wikipedia.org/wiki/Database);
- Highly customisable config - you can change all the messages the plugin sends, or just disable them;
- [MCEdit](https://www.mcedit.net/about.html) schematic world loading;
- And more...

## Commands and permissions

### Player

- ***limboauth.commands.destroysession* | /destroysession** - Destroy Account Auth Session Command
- ***limboauth.commands.premium* | /license or /premium** - Command Makes Account Premium
- ***limboauth.commands.unregister* | /unregister** - Unregister Account Command
- ***limboauth.commands.changepassword* | /changepassword** - Change Account Password Command
- ***limboauth.commands.totp* | /totp** - 2FA Management Command
- ***limboauth.commands.***\* - Gives All Player Permissions

### Admin

- ***limboauth.admin.forceunregister* | /forceunregister** - Force Unregister Account Command
- ***limboauth.admin.forcechangepassword* | /forcechangepassword** - Force Change Account Password Command
- ***limboauth.admin.forceregister* | /forceregister** - Force Registration Account Command
- ***limboauth.admin.reload* | /lauth reload** - Reload Plugin Command
- ***limboauth.admin.***\* - Gives All Admin Permissions

## Donation

Implemented based on [LimboAuth](https://github.com/Elytrium/LimboAuth)
