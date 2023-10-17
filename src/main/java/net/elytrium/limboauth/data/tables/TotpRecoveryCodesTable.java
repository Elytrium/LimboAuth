package net.elytrium.limboauth.data.tables;

import java.util.UUID;
import org.jooq.Record;
import org.jooq.TableField;
import org.jooq.impl.DSL;
import org.jooq.impl.SQLDataType;
import org.jooq.impl.TableImpl;

public class TotpRecoveryCodesTable extends TableImpl<Record> {

  public static final TotpRecoveryCodesTable INSTANCE = new TotpRecoveryCodesTable();

  public static final TableField<Record, UUID> OWNER = TableImpl.createField(DSL.name("OWNER"), SQLDataType.UUID.nullable(false), TotpRecoveryCodesTable.INSTANCE);
  public static final TableField<Record, String> CODE = TableImpl.createField(DSL.name("CODE"), SQLDataType.VARCHAR().nullable(false), TotpRecoveryCodesTable.INSTANCE);

  private TotpRecoveryCodesTable() {
    super(DSL.name("TOTP_RECOVERY_CODES"));
  }
}
