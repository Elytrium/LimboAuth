/*
 * Copyright (C) 2023-2024 Elytrium
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
  public static final TableField<Record, String> CODE = TableImpl.createField(DSL.name("CODE"), SQLDataType.VARCHAR(17).nullable(false), TotpRecoveryCodesTable.INSTANCE);

  private TotpRecoveryCodesTable() {
    super(DSL.name("TOTP_RECOVERY_CODES"));
  }
}
