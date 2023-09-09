package net.elytrium.limboauth.model;

import com.google.gson.JsonObject;
import com.google.gson.JsonParser;
import com.j256.ormlite.field.DatabaseField;
import com.j256.ormlite.table.DatabaseTable;

@DatabaseTable(tableName = "cms_members")
public class CMSUser {
    @DatabaseField(columnName = "member_id", id = true)
    private int memberId;
    @DatabaseField(columnName = "name")
    private String username;
    @DatabaseField(columnName = "email")
    private String email;
    @DatabaseField(columnName = "ip_address")
    private String ipAddress;
    @DatabaseField(columnName = "members_pass_hash")
    private String passwordHash;
    @DatabaseField(columnName = "members_bitoptions")
    private int bitOptions;
    @DatabaseField(columnName = "mfa_details")
    private String mfaDetails;

    public CMSUser() {
    }

    public int getMemberId() {
        return memberId;
    }

    public String getUsername() {
        return username;
    }

    public String getEmail() {
        return email;
    }

    public String getIpAddress() {
        return ipAddress;
    }

    public String getPasswordHash() {
        // `$2y$` -> `$2a$` from https://invisioncommunity.com/forums/topic/466097-where-is-password-salt-stored/?do=findComment&comment=2884363
        return passwordHash.replace("$2y$", "$2a$");
    }

    public String setPasswordHash(String passwordHash) {
        return this.passwordHash = passwordHash.replace("$2a$", "$2y$");
    }

    public int getBitOptions() {
        return bitOptions;
    }

    public boolean hasVerifiedEmail() {
        return (bitOptions & 1 << 30) == 0;
    }

    public String getMfaDetails() {
        return mfaDetails;
    }

    public String getTotpToken() {
        return JsonParser.parseString(mfaDetails).getAsJsonObject().get("google").getAsString();
    }

    public void setTotpToken(String totpToken) {
        JsonObject mfaDetails = JsonParser.parseString(this.mfaDetails).getAsJsonObject();
        mfaDetails.addProperty("google", totpToken);
        this.mfaDetails = mfaDetails.toString();
    }
}
