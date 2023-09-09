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

import at.favre.lib.crypto.bcrypt.BCrypt;
import com.google.gson.JsonObject;
import com.google.gson.JsonParser;
import com.j256.ormlite.db.DatabaseType;
import com.j256.ormlite.field.DatabaseFieldConfig;
import com.j256.ormlite.table.DatabaseTable;
import com.j256.ormlite.table.DatabaseTableConfig;
import com.velocitypowered.api.proxy.Player;
import java.net.InetSocketAddress;
import java.util.ArrayList;
import java.util.List;
import java.util.Locale;
import java.util.UUID;
import net.elytrium.limboauth.Settings;

@DatabaseTable(tableName = "AUTH")
public class RegisteredPlayer {

  private static final BCrypt.Hasher HASHER = BCrypt.withDefaults();

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

  private CMSUser cmsLinkedMember;

  @Deprecated
  public RegisteredPlayer(String nickname, String hash, String ip, String totpToken, Long regDate, String uuid, String premiumUuid, String loginIp, Long loginDate) {
    this.lowercaseNickname = nickname.toLowerCase(Locale.ROOT);
    this.hash = hash;
    this.ip = ip;
    // Use setter for deserialization
    this.setTotpToken(totpToken);
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
    this.lowercaseNickname = nickname.toLowerCase(Locale.ROOT);
    this.uuid = uuid;
    this.ip = ip;
    this.loginIp = ip;
  }

  public RegisteredPlayer() {

  }

  public static String genHash(String password) {
    return HASHER.hashToString(Settings.IMP.MAIN.BCRYPT_COST, password.toCharArray());
  }

  public RegisteredPlayer setNickname(String nickname) {
    this.lowercaseNickname = nickname.toLowerCase(Locale.ROOT);

    return this;
  }

  public String getNickname() {
    return this.lowercaseNickname;
  }

  public String getLowercaseNickname() {
    return this.lowercaseNickname;
  }

  public RegisteredPlayer setPassword(String password) {
    return setHash(genHash(password));
  }

  public RegisteredPlayer setHash(String hash) {
    if (cmsLinkedMember != null) {
      cmsLinkedMember.setPasswordHash(hash);
    }
    this.hash = hash;
    this.tokenIssuedAt = System.currentTimeMillis();

    return this;
  }

  public String getHash() {
    if (cmsLinkedMember != null) {
      return cmsLinkedMember.getPasswordHash();
    }
    return this.hash == null ? "" : this.hash;
  }

  public RegisteredPlayer setIP(String ip) {
    this.ip = ip;

    return this;
  }

  public String getIP() {
    return this.ip == null ? "" : this.ip;
  }

  public RegisteredPlayer setTotpToken(String totpToken) {
    if (cmsLinkedMember != null) {
      cmsLinkedMember.setTotpToken(totpToken);
    }

    this.totpToken = totpToken;

    return this;
  }

  public String getTotpToken() {
    if (cmsLinkedMember != null) {
      return cmsLinkedMember.getTotpToken();
    }
    return this.totpToken == null ? "" : this.totpToken;
  }

  public RegisteredPlayer setRegDate(Long regDate) {
    this.regDate = regDate;

    return this;
  }

  public long getRegDate() {
    return this.regDate == null ? Long.MIN_VALUE : this.regDate;
  }

  public RegisteredPlayer setUuid(String uuid) {
    this.uuid = uuid;

    return this;
  }

  public String getUuid() {
    return uuid == null ? "" : uuid;
  }

  public RegisteredPlayer setPremiumUuid(String premiumUuid) {
    this.premiumUuid = premiumUuid;

    return this;
  }

  public RegisteredPlayer setPremiumUuid(UUID premiumUuid) {
    this.premiumUuid = premiumUuid.toString();

    return this;
  }

  public String getPremiumUuid() {
    return this.premiumUuid == null ? "" : this.premiumUuid;
  }

  public String getLoginIp() {
    return this.loginIp == null ? "" : this.loginIp;
  }

  public RegisteredPlayer setLoginIp(String loginIp) {
    this.loginIp = loginIp;

    return this;
  }

  public long getLoginDate() {
    return this.loginDate == null ? Long.MIN_VALUE : this.loginDate;
  }

  public RegisteredPlayer setLoginDate(Long loginDate) {
    this.loginDate = loginDate;

    return this;
  }

  public long getTokenIssuedAt() {
    return this.tokenIssuedAt == null ? Long.MIN_VALUE : this.tokenIssuedAt;
  }

  public RegisteredPlayer setTokenIssuedAt(Long tokenIssuedAt) {
    this.tokenIssuedAt = tokenIssuedAt;

    return this;
  }

  public CMSUser getCmsLinkedMember() {
    return cmsLinkedMember;
  }

  public static DatabaseTableConfig<RegisteredPlayer> buildPlayerTableConfig(DatabaseType databaseType) {
    List<DatabaseFieldConfig> fieldConfigs = new ArrayList<>();

    DatabaseFieldConfig fieldConfig = new DatabaseFieldConfig("lowercaseNickname");
    fieldConfig.setId(true);
    fieldConfig.setColumnName(Settings.IMP.DATABASE.COLUMN_NAMES.LOWERCASE_NICKNAME_FIELD);
    fieldConfigs.add(fieldConfig);

    // Reuse same variable
    fieldConfig = new DatabaseFieldConfig("hash");
    fieldConfig.setColumnName(Settings.IMP.DATABASE.COLUMN_NAMES.HASH_FIELD);
    fieldConfigs.add(fieldConfig);

    fieldConfig = new DatabaseFieldConfig("ip");
    fieldConfig.setColumnName(Settings.IMP.DATABASE.COLUMN_NAMES.IP_FIELD);
    fieldConfigs.add(fieldConfig);

    fieldConfig = new DatabaseFieldConfig("totpToken");
    fieldConfig.setColumnName(Settings.IMP.DATABASE.COLUMN_NAMES.TOTP_TOKEN_FIELD);
    fieldConfigs.add(fieldConfig);

    fieldConfig = new DatabaseFieldConfig("regDate");
    fieldConfig.setColumnName(Settings.IMP.DATABASE.COLUMN_NAMES.REG_DATE_FIELD);
    fieldConfigs.add(fieldConfig);

    fieldConfig = new DatabaseFieldConfig("uuid");
    fieldConfig.setColumnName(Settings.IMP.DATABASE.COLUMN_NAMES.UUID_FIELD);
    fieldConfigs.add(fieldConfig);

    fieldConfig = new DatabaseFieldConfig("premiumUuid");
    fieldConfig.setColumnName(Settings.IMP.DATABASE.COLUMN_NAMES.PREMIUM_UUID_FIELD);
    fieldConfigs.add(fieldConfig);

    fieldConfig = new DatabaseFieldConfig("loginIp");
    fieldConfig.setColumnName(Settings.IMP.DATABASE.COLUMN_NAMES.LOGIN_IP_FIELD);
    fieldConfigs.add(fieldConfig);

    fieldConfig = new DatabaseFieldConfig("loginDate");
    fieldConfig.setColumnName(Settings.IMP.DATABASE.COLUMN_NAMES.LOGIN_DATE_FIELD);
    fieldConfigs.add(fieldConfig);

    fieldConfig = new DatabaseFieldConfig("tokenIssuedAt");
    fieldConfig.setColumnName(Settings.IMP.DATABASE.COLUMN_NAMES.TOKEN_ISSUED_AT_FIELD);
    fieldConfigs.add(fieldConfig);

    fieldConfig = new DatabaseFieldConfig("cmsLinkedMember");
    fieldConfig.setColumnName(Settings.IMP.DATABASE.COLUMN_NAMES.CMS_LINKED_MEMBER);
    fieldConfig.setForeign(true);
    fieldConfig.setForeignAutoRefresh(true);
    // Handle deletion of CMS user
    fieldConfig.setColumnDefinition("INTEGER CONSTRAINT FK_CMS_LINKED_MEMBER REFERENCES core_members(member_id) ON DELETE SET NULL");
    fieldConfigs.add(fieldConfig);

    DatabaseTableConfig<RegisteredPlayer> tableConfig = new DatabaseTableConfig<>(databaseType, RegisteredPlayer.class, fieldConfigs);
    tableConfig.setTableName(Settings.IMP.DATABASE.TABLE_NAME);
    return tableConfig;
  }
}
