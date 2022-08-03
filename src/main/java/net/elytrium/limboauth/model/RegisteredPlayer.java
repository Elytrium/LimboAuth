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

package net.elytrium.limboauth.model;

import com.j256.ormlite.field.DatabaseField;
import com.j256.ormlite.table.DatabaseTable;

@DatabaseTable(tableName = "AUTH")
public class RegisteredPlayer {

  @DatabaseField(canBeNull = false, columnName = "NICKNAME")
  private String nickname;

  @DatabaseField(id = true, columnName = "LOWERCASENICKNAME")
  private String lowercaseNickname;

  @DatabaseField(canBeNull = false, columnName = "HASH")
  private String hash;

  @DatabaseField(canBeNull = false, columnName = "PREMIUM")
  private boolean premium;

  @DatabaseField(columnName = "IP")
  private String ip;

  @DatabaseField(columnName = "TOTPTOKEN")
  private String totpToken;

  @DatabaseField(columnName = "REGDATE")
  private Long regDate;

  @DatabaseField(columnName = "UUID")
  private String uuid;

  @DatabaseField(columnName = "PREMIUMUUID")
  private String premiumUuid;

  public RegisteredPlayer(String nickname, String lowercaseNickname,
      String hash, boolean premium, String ip, String totpToken, Long regDate, String uuid, String premiumUuid) {
    this.nickname = nickname;
    this.lowercaseNickname = lowercaseNickname;
    this.hash = hash;
    this.premium = premium;
    this.ip = ip;
    this.totpToken = totpToken;
    this.regDate = regDate;
    this.uuid = uuid;
    this.premiumUuid = premiumUuid;
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

  public void setPremium(boolean premium) {
    this.premium = premium;
  }

  public boolean getPremium() {
    return this.premium;
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

  public Long getRegDate() {
    return this.regDate == null ? (Long) Long.MIN_VALUE : this.regDate;
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
}
