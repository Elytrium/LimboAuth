/*
 * Copyright (C) 2021 - 2025 Elytrium
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

package net.elytrium.limboauth.model;

import at.favre.lib.crypto.bcrypt.BCrypt;
import com.velocitypowered.api.proxy.Player;
import lombok.*;
import net.elytrium.limboauth.Settings;

import java.net.InetSocketAddress;
import java.util.Locale;
import java.util.UUID;

@Getter
@Setter
@Builder
@AllArgsConstructor(access = AccessLevel.PROTECTED)
public class RegisteredPlayer {

    private static final BCrypt.Hasher HASHER = BCrypt.withDefaults();

    private String nickname;

    private String lowercaseNickname;

    private String hash = "";

    private String ip;

    private String totpToken = "";

    private Long regDate = System.currentTimeMillis();

    private String uuid = "";

    private String premiumUuid = "";

    private String loginIp;

    private Long loginDate = System.currentTimeMillis();

    private Long tokenIssuedAt = System.currentTimeMillis();

    @Deprecated
    public RegisteredPlayer(String nickname, String lowercaseNickname,
                            String hash, String ip, String totpToken, Long regDate, String uuid, String premiumUuid, String loginIp, Long loginDate) {
        this.nickname = nickname;
        this.lowercaseNickname = lowercaseNickname;
        this.hash = hash;
        this.ip = ip;
        this.totpToken = totpToken;
        this.regDate = regDate;
        this.uuid = uuid;
        this.premiumUuid = premiumUuid;
        this.loginIp = loginIp;
        this.loginDate = loginDate;
    }

    public RegisteredPlayer(Player player) {
        this(player.getUsername(), player.getUniqueId(), player.getRemoteAddress());
    }

    public RegisteredPlayer(String nickname, UUID uuid, InetSocketAddress ip) {
        this(nickname, uuid.toString(), ip.getAddress().getHostAddress());
    }

    public RegisteredPlayer(String nickname, String uuid, String ip) {
        this.nickname = nickname;
        this.lowercaseNickname = nickname.toLowerCase(Locale.ROOT);
        this.uuid = uuid;
        this.ip = ip;
        this.loginIp = ip;
    }


    public static String genHash(String password) {
        return HASHER.hashToString(Settings.IMP.MAIN.BCRYPT_COST, password.toCharArray());
    }

    public RegisteredPlayer setNickname(String nickname) {
        this.nickname = nickname;
        this.lowercaseNickname = nickname.toLowerCase(Locale.ROOT);

        return this;
    }

    public RegisteredPlayer setPremiumUuid(UUID premiumUuid) {
        this.premiumUuid = premiumUuid.toString();
        return this;
    }

    public RegisteredPlayer setPremiumUuid(String premiumUuid) {
        this.premiumUuid = premiumUuid;
        return this;
    }

    public String getNickname() {
        return this.nickname == null ? this.lowercaseNickname : this.nickname;
    }

    public RegisteredPlayer setPassword(String password) {
        this.hash = genHash(password);
        this.tokenIssuedAt = System.currentTimeMillis();

        return this;
    }

    public RegisteredPlayer setHash(String hash) {
        this.hash = hash;
        this.tokenIssuedAt = System.currentTimeMillis();

        return this;
    }

    public String getHash() {
        return this.hash == null ? "" : this.hash;
    }

    public String getIP() {
        return this.ip == null ? "" : this.ip;
    }

    public String getTotpToken() {
        return this.totpToken == null ? "" : this.totpToken;
    }

    public long getRegDate() {
        return this.regDate == null ? Long.MIN_VALUE : this.regDate;
    }

    public String getUuid() {
        return this.uuid == null ? "" : this.uuid;
    }

    public String getPremiumUuid() {
        return this.premiumUuid == null ? "" : this.premiumUuid;
    }

    public String getLoginIp() {
        return this.loginIp == null ? "" : this.loginIp;
    }

    public long getLoginDate() {
        return this.loginDate == null ? Long.MIN_VALUE : this.loginDate;
    }

    public long getTokenIssuedAt() {
        return this.tokenIssuedAt == null ? Long.MIN_VALUE : this.tokenIssuedAt;
    }

}
