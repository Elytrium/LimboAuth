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

package net.elytrium.limboauth.model;

import com.j256.ormlite.field.DatabaseField;
import com.j256.ormlite.table.DatabaseTable;

@DatabaseTable(tableName = "AUTH")
public class RegisteredPlayer {

  public static final String NICKNAME_FIELD = "NICKNAME";
  public static final String LOWERCASE_NICKNAME_FIELD = "LOWERCASENICKNAME";
  public static final String HASH_FIELD = "HASH";
  public static final String IP_FIELD = "IP";
  public static final String LOGIN_IP_FIELD = "LOGINIP";
  public static final String TOTP_TOKEN_FIELD = "TOTPTOKEN";
  public static final String REG_DATE_FIELD = "REGDATE";
  public static final String LOGIN_DATE_FIELD = "LOGINDATE";
  public static final String UUID_FIELD = "UUID";
  public static final String PREMIUM_UUID_FIELD = "PREMIUMUUID";

  @DatabaseField(canBeNull = false, columnName = NICKNAME_FIELD)
  private String nickname;

  @DatabaseField(id = true, columnName = LOWERCASE_NICKNAME_FIELD)
  private String lowercaseNickname;

  @DatabaseField(canBeNull = false, columnName = HASH_FIELD)
  private String hash;

  @DatabaseField(columnName = IP_FIELD)
  private String ip;

  @DatabaseField(columnName = TOTP_TOKEN_FIELD)
  private String totpToken;

  @DatabaseField(columnName = REG_DATE_FIELD)
  private Long regDate;

  @DatabaseField(columnName = UUID_FIELD)
  private String uuid;

  @DatabaseField(columnName = RegisteredPlayer.PREMIUM_UUID_FIELD)
  private String premiumUuid;

  @DatabaseField(columnName = LOGIN_IP_FIELD)
  private String loginIp;

  @DatabaseField(columnName = LOGIN_DATE_FIELD)
  private Long loginDate;

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

  public RegisteredPlayer() {

  }

  public void setNickname(String nickname) {
    this.nickname = nickname;
  }

  public String getNickname() {
    return this.nickname == null ? this.lowercaseNickname : this.nickname;
  }

  public void setLowercaseNickname(String lowercaseNickname) {
    this.lowercaseNickname = lowercaseNickname;
  }

  public String getLowercaseNickname() {
    return this.lowercaseNickname;
  }

  public void setHash(String hash) {
    this.hash = hash;
  }

  public String getHash() {
    return this.hash == null ? "" : this.hash;
  }

  public void setIP(String ip) {
    this.ip = ip;
  }

  public String getIP() {
    return this.ip == null ? "" : this.ip;
  }

  public void setTotpToken(String totpToken) {
    this.totpToken = totpToken;
  }

  public String getTotpToken() {
    return this.totpToken == null ? "" : this.totpToken;
  }

  public void setRegDate(Long regDate) {
    this.regDate = regDate;
  }

  public long getRegDate() {
    return this.regDate == null ? Long.MIN_VALUE : this.regDate;
  }

  public void setUuid(String uuid) {
    this.uuid = uuid;
  }

  public String getUuid() {
    return this.uuid == null ? "" : this.uuid;
  }

  public void setPremiumUuid(String premiumUuid) {
    this.premiumUuid = premiumUuid;
  }

  public String getPremiumUuid() {
    return this.premiumUuid == null ? "" : this.premiumUuid;
  }

  public String getLoginIp() {
    return this.loginIp == null ? "" : this.uuid;
  }

  public void setLoginIp(String loginIp) {
    this.loginIp = loginIp;
  }

  public long getLoginDate() {
    return this.loginDate == null ? Long.MIN_VALUE : this.loginDate;
  }

  public void setLoginDate(Long loginDate) {
    this.loginDate = loginDate;
  }
}
