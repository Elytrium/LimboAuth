/*
 * Copyright (C) 2021-2024 Elytrium
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
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

package net.elytrium.limboauth.data;

import com.velocitypowered.api.proxy.Player;
import java.net.InetSocketAddress;
import java.nio.charset.StandardCharsets;
import java.util.Locale;
import java.util.UUID;
import net.elytrium.limboauth.Settings;
import net.elytrium.limboauth.utils.Random;
import org.bouncycastle.crypto.generators.OpenBSDBCrypt;
import org.jooq.Field;
import org.jooq.Record12;
import org.jooq.Row12;
import org.jooq.TableField;
import org.jooq.impl.DSL;
import org.jooq.impl.SQLDataType;
import org.jooq.impl.TableImpl;
import org.jooq.impl.UpdatableRecordImpl;

@SuppressWarnings("NullableProblems")
public class PlayerData extends UpdatableRecordImpl<PlayerData> implements Record12<String, String, String, String, String, Long, UUID, UUID, String, Long, Long, Boolean> { // TODO get rid

  private String nickname;

  private String lowercaseNickname;

  private String hash = "";

  private String ip;

  private String totpToken = "";

  private Long regDate = System.currentTimeMillis();

  private UUID uuid;

  private UUID premiumUuid;

  private String loginIp;

  private Long loginDate = System.currentTimeMillis();

  private Long tokenIssuedAt = System.currentTimeMillis();

  private boolean onlyByMod = false;

  public PlayerData(Player player) {
    this(player.getUsername(), player.getUniqueId(), player.getRemoteAddress());
  }

  public PlayerData(String nickname, UUID uuid, InetSocketAddress ip) {
    this(nickname, uuid, ip.getAddress().getHostAddress());
  }

  public PlayerData(String nickname, String uuid, String ip) {
    this(nickname, UUID.fromString(uuid), ip);
  }

  public PlayerData(String nickname, UUID uuid, String ip) {
    super(Table.INSTANCE);
    this.nickname = nickname;
    this.lowercaseNickname = nickname.toLowerCase(Locale.ROOT);
    this.uuid = uuid;
    this.ip = ip;
    this.loginIp = ip;
  }

  public static String genHash(String password) {
    return OpenBSDBCrypt.generate(password.getBytes(StandardCharsets.UTF_8), Random.nextBytes(16), Settings.HEAD.bcryptCost);
  }

  public PlayerData setNickname(String nickname) {
    this.nickname = nickname;
    this.lowercaseNickname = nickname.toLowerCase(Locale.ROOT);
    return this;
  }

  public String getNickname() {
    return this.nickname == null ? this.lowercaseNickname : this.nickname;
  }

  public String getLowercaseNickname() {
    return this.lowercaseNickname;
  }

  public PlayerData setPassword(String password) {
    this.hash = genHash(password);
    this.tokenIssuedAt = System.currentTimeMillis();

    return this;
  }

  public PlayerData setHash(String hash) {
    this.hash = hash;
    this.tokenIssuedAt = System.currentTimeMillis();

    return this;
  }

  public String getHash() {
    return this.hash == null ? "" : this.hash;
  }

  public PlayerData setIP(String ip) {
    this.ip = ip;

    return this;
  }

  public String getIP() {
    return this.ip == null ? "" : this.ip;
  }

  public PlayerData setTotpToken(String totpToken) {
    this.totpToken = totpToken;

    return this;
  }

  public String getTotpToken() {
    return this.totpToken == null ? "" : this.totpToken;
  }

  public PlayerData setRegDate(Long regDate) {
    this.regDate = regDate;

    return this;
  }

  public long getRegDate() {
    return this.regDate == null ? Long.MIN_VALUE : this.regDate;
  }

  public PlayerData setUuid(String uuid) {
    this.uuid = UUID.fromString(uuid);
    return this;
  }

  public PlayerData setUuid(UUID uuid) {
    this.uuid = uuid;
    return this;
  }

  public UUID getUuid() {
    return this.uuid;
  }

  public PlayerData setPremiumUuid(String premiumUuid) {
    this.premiumUuid = UUID.fromString(premiumUuid);
    return this;
  }

  public PlayerData setPremiumUuid(UUID premiumUuid) {
    this.premiumUuid = premiumUuid;
    return this;
  }

  public UUID getPremiumUuid() {
    return this.premiumUuid;
  }

  public String getLoginIp() {
    return this.loginIp == null ? "" : this.loginIp;
  }

  public PlayerData setLoginIp(String loginIp) {
    this.loginIp = loginIp;
    return this;
  }

  public long getLoginDate() {
    return this.loginDate == null ? Long.MIN_VALUE : this.loginDate;
  }

  public PlayerData setLoginDate(Long loginDate) {
    this.loginDate = loginDate;
    return this;
  }

  public long getTokenIssuedAt() {
    return this.tokenIssuedAt == null ? Long.MIN_VALUE : this.tokenIssuedAt;
  }

  public PlayerData setTokenIssuedAt(Long tokenIssuedAt) {
    this.tokenIssuedAt = tokenIssuedAt;
    return this;
  }

  public boolean isOnlyByMod() {
    return this.onlyByMod;
  }

  public PlayerData setOnlyByMod(boolean onlyByMod) {
    this.onlyByMod = onlyByMod;
    return this;
  }

  @Override
  public Field<String> field1() {
    return Table.NICKNAME_FIELD;
  }

  @Override
  public Field<String> field2() {
    return Table.LOWERCASE_NICKNAME_FIELD;
  }

  @Override
  public Field<String> field3() {
    return Table.HASH_FIELD;
  }

  @Override
  public Field<String> field4() {
    return Table.IP_FIELD;
  }

  @Override
  public Field<String> field5() {
    return Table.TOTP_TOKEN_FIELD;
  }

  @Override
  public Field<Long> field6() {
    return Table.REG_DATE_FIELD;
  }

  @Override
  public Field<UUID> field7() {
    return Table.UUID_FIELD;
  }

  @Override
  public Field<UUID> field8() {
    return Table.PREMIUM_UUID_FIELD;
  }

  @Override
  public Field<String> field9() {
    return Table.LOGIN_IP_FIELD;
  }

  @Override
  public Field<Long> field10() {
    return Table.LOGIN_DATE_FIELD;
  }

  @Override
  public Field<Long> field11() {
    return Table.TOKEN_ISSUED_AT_FIELD;
  }

  @Override
  public Field<Boolean> field12() {
    return Table.ONLY_BY_MOD_FIELD;
  }

  @Override
  public String value1() {
    return this.nickname;
  }

  @Override
  public PlayerData value1(String value) {
    return this.setNickname(value);
  }

  @Override
  public String value2() {
    return this.lowercaseNickname;
  }

  @Override
  public PlayerData value2(String value) {
    this.lowercaseNickname = value;
    return this;
  }

  @Override
  public String value3() {
    return this.hash;
  }

  @Override
  public PlayerData value3(String value) {
    return this.setHash(value);
  }

  @Override
  public String value4() {
    return this.ip;
  }

  @Override
  public PlayerData value4(String value) {
    return this.setIP(value);
  }

  @Override
  public String value5() {
    return this.totpToken;
  }

  @Override
  public PlayerData value5(String value) {
    return this.setTotpToken(value);
  }

  @Override
  public Long value6() {
    return this.regDate;
  }

  @Override
  public PlayerData value6(Long value) {
    return this.setRegDate(value);
  }

  @Override
  public UUID value7() {
    return this.uuid;
  }

  @Override
  public PlayerData value7(UUID value) {
    return this.setUuid(value);
  }

  @Override
  public UUID value8() {
    return this.premiumUuid;
  }

  @Override
  public PlayerData value8(UUID value) {
    return this.setPremiumUuid(value);
  }

  @Override
  public String value9() {
    return this.loginIp;
  }

  @Override
  public PlayerData value9(String value) {
    return this.setLoginIp(value);
  }

  @Override
  public Long value10() {
    return this.loginDate;
  }

  @Override
  public PlayerData value10(Long value) {
    return this.setLoginDate(value);
  }

  @Override
  public Long value11() {
    return this.tokenIssuedAt;
  }

  @Override
  public PlayerData value11(Long value) {
    return this.setTokenIssuedAt(value);
  }

  @Override
  public Boolean value12() {
    return this.onlyByMod;
  }

  @Override
  public PlayerData value12(Boolean value) {
    return this.setOnlyByMod(value);
  }

  @Override
  public PlayerData values(String nickname, String lowercaseNickname, String hash, String ip, String totpToken,
      Long regDate, UUID uuid, UUID premiumUuid, String loginIp, Long loginDate, Long tokenIssuedAt, Boolean onlyByMod) {
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
    this.tokenIssuedAt = tokenIssuedAt;
    this.onlyByMod = onlyByMod;

    return this;
  }

  @Override
  public String component1() {
    return this.nickname;
  }

  @Override
  public String component2() {
    return this.lowercaseNickname;
  }

  @Override
  public String component3() {
    return this.hash;
  }

  @Override
  public String component4() {
    return this.ip;
  }

  @Override
  public String component5() {
    return this.totpToken;
  }

  @Override
  public Long component6() {
    return this.regDate;
  }

  @Override
  public UUID component7() {
    return this.uuid;
  }

  @Override
  public UUID component8() {
    return this.premiumUuid;
  }

  @Override
  public String component9() {
    return this.loginIp;
  }

  @Override
  public Long component10() {
    return this.loginDate;
  }

  @Override
  public Long component11() {
    return this.tokenIssuedAt;
  }

  @Override
  public Boolean component12() {
    return this.onlyByMod;
  }

  @Override
  @SuppressWarnings("unchecked")
  public Row12<String, String, String, String, String, Long, UUID, UUID, String, Long, Long, Boolean> fieldsRow() {
    return (Row12<String, String, String, String, String, Long, UUID, UUID, String, Long, Long, Boolean>) super.fieldsRow();
  }

  @Override
  @SuppressWarnings("unchecked")
  public Row12<String, String, String, String, String, Long, UUID, UUID, String, Long, Long, Boolean> valuesRow() {
    return (Row12<String, String, String, String, String, Long, UUID, UUID, String, Long, Long, Boolean>) super.valuesRow();
  }

  @Override
  public boolean equals(Object o) {
    return super.equals(o);
  }

  @Override
  public int hashCode() {
    return super.hashCode();
  }

  public static class Table extends TableImpl<PlayerData> {

    public static final Table INSTANCE = new Table();

    // TODO inline
    public static final TableField<PlayerData, String> NICKNAME_FIELD = TableImpl.createField(DSL.name(Settings.DATABASE.table.nicknameField), SQLDataType.VARCHAR.length(16), Table.INSTANCE);
    public static final TableField<PlayerData, String> LOWERCASE_NICKNAME_FIELD = TableImpl.createField(
        DSL.name(Settings.DATABASE.table.lowercaseNicknameField),
        SQLDataType.VARCHAR.length(16).nullable(false),
        Table.INSTANCE
    );
    public static final TableField<PlayerData, String> HASH_FIELD = TableImpl.createField(DSL.name(Settings.DATABASE.table.hashField), SQLDataType.VARCHAR, Table.INSTANCE);
    public static final TableField<PlayerData, String> IP_FIELD = TableImpl.createField(DSL.name(Settings.DATABASE.table.ipField), SQLDataType.VARCHAR, Table.INSTANCE);
    public static final TableField<PlayerData, String> TOTP_TOKEN_FIELD = TableImpl.createField(DSL.name(Settings.DATABASE.table.totpTokenField), SQLDataType.VARCHAR, Table.INSTANCE);
    public static final TableField<PlayerData, Long> REG_DATE_FIELD = TableImpl.createField(DSL.name(Settings.DATABASE.table.regDateField), SQLDataType.BIGINT, Table.INSTANCE);
    public static final TableField<PlayerData, UUID> UUID_FIELD = TableImpl.createField(DSL.name(Settings.DATABASE.table.uuidField), SQLDataType.UUID, Table.INSTANCE);
    // TODO default null ⌄
    public static final TableField<PlayerData, UUID> PREMIUM_UUID_FIELD = TableImpl.createField(DSL.name(Settings.DATABASE.table.premiumUuidField), SQLDataType.UUID, Table.INSTANCE);
    public static final TableField<PlayerData, String> LOGIN_IP_FIELD = TableImpl.createField(DSL.name(Settings.DATABASE.table.loginIpField), SQLDataType.VARCHAR, Table.INSTANCE);
    public static final TableField<PlayerData, Long> LOGIN_DATE_FIELD = TableImpl.createField(DSL.name(Settings.DATABASE.table.loginDateField), SQLDataType.BIGINT, Table.INSTANCE);
    public static final TableField<PlayerData, Long> TOKEN_ISSUED_AT_FIELD = TableImpl.createField(DSL.name(Settings.DATABASE.table.tokenIssuedAtField), SQLDataType.BIGINT, Table.INSTANCE);
    public static final TableField<PlayerData, Boolean> ONLY_BY_MOD_FIELD = TableImpl.createField(DSL.name(Settings.DATABASE.table.onlyByModField), SQLDataType.BOOLEAN, Table.INSTANCE);

    public Table() {
      super(DSL.name("AUTH"/*TODO configurable?*/));
    }
  }
}
