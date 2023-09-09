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

package net.elytrium.limboauth.command;

import com.velocitypowered.api.command.CommandSource;
import com.velocitypowered.api.command.SimpleCommand;
import com.velocitypowered.api.proxy.Player;
import dev.samstevens.totp.qr.QrData;
import dev.samstevens.totp.recovery.RecoveryCodeGenerator;
import dev.samstevens.totp.secret.DefaultSecretGenerator;
import dev.samstevens.totp.secret.SecretGenerator;
import java.net.URLEncoder;
import java.nio.charset.StandardCharsets;
import java.util.Locale;
import net.elytrium.limboauth.LimboAuth;
import net.elytrium.limboauth.Settings;
import net.elytrium.limboauth.handler.AuthSessionHandler;
import net.elytrium.limboauth.model.RegisteredPlayer;
import net.elytrium.serializer.placeholders.Placeholders;
import net.kyori.adventure.text.Component;
import net.kyori.adventure.text.event.ClickEvent;
import org.jooq.DSLContext;
import org.jooq.impl.DSL;

public class TotpCommand implements SimpleCommand {

  private final SecretGenerator secretGenerator = new DefaultSecretGenerator();
  private final RecoveryCodeGenerator codesGenerator = new RecoveryCodeGenerator();
  private final LimboAuth plugin;
  private final DSLContext dslContext;

  public TotpCommand(LimboAuth plugin, DSLContext dslContext) {
    this.plugin = plugin;
    this.dslContext = dslContext;
  }

  // TODO: Rewrite.
  @Override
  public void execute(SimpleCommand.Invocation invocation) {
    CommandSource source = invocation.source();
    String[] args = invocation.arguments();

    if (source instanceof Player) {
      if (args.length == 0) {
        source.sendMessage(Settings.MESSAGES.totpUsage);
      } else {
        String username = ((Player) source).getUsername();
        String lowercaseNickname = username.toLowerCase(Locale.ROOT);

        if (args[0].equalsIgnoreCase("enable")) {
          if (Settings.IMP.totpNeedPassword ? args.length == 2 : args.length == 1) {
            RegisteredPlayer.checkPassword(this.dslContext, lowercaseNickname, Settings.IMP.totpNeedPassword ? args[1] : null,
                () -> source.sendMessage(Settings.MESSAGES.notRegistered),
                () -> source.sendMessage(Settings.MESSAGES.crackedCommand),
                h -> this.dslContext.selectFrom(RegisteredPlayer.Table.INSTANCE)
                    .where(DSL.field(RegisteredPlayer.Table.LOWERCASE_NICKNAME_FIELD).eq(username))
                    .fetchAsync()
                    .thenAccept(totpTokenResult -> {
                      if (totpTokenResult.isEmpty() || totpTokenResult.get(0).value1().isEmpty()) {
                        String secret = this.secretGenerator.generate();
                        this.dslContext.update(RegisteredPlayer.Table.INSTANCE)
                            .set(RegisteredPlayer.Table.TOTP_TOKEN_FIELD, secret)
                            .where(DSL.field(RegisteredPlayer.Table.LOWERCASE_NICKNAME_FIELD).eq(username))
                            .executeAsync()
                            .thenRun(() -> source.sendMessage(Settings.MESSAGES.totpSuccessful))
                            .exceptionally(e -> {
                              this.plugin.handleSqlError(e);
                              source.sendMessage(Settings.MESSAGES.errorOccurred);
                              return null;
                            });

                        QrData data = new QrData.Builder()
                            .label(username)
                            .secret(secret)
                            .issuer(Settings.IMP.totpIssuer)
                            .build();
                        String qrUrl = Placeholders.replace(Settings.IMP.qrGeneratorUrl, URLEncoder.encode(data.getUri(), StandardCharsets.UTF_8));
                        source.sendMessage(Settings.MESSAGES.totpQr.clickEvent(ClickEvent.openUrl(qrUrl)));

                        source.sendMessage(((Component) Placeholders.replace(Settings.MESSAGES.totpToken, secret))
                            .clickEvent(ClickEvent.copyToClipboard(secret)));
                        String codes = String.join(", ", this.codesGenerator.generateCodes(Settings.IMP.totpRecoveryCodesAmount));
                        source.sendMessage(((Component) Placeholders.replace(Settings.MESSAGES.totpRecovery, codes))
                            .clickEvent(ClickEvent.copyToClipboard(codes)));
                      } else {
                        source.sendMessage(Settings.MESSAGES.totpAlreadyEnabled);
                      }
                    })
                    .exceptionally(e -> {
                      this.plugin.handleSqlError(e);
                      source.sendMessage(Settings.MESSAGES.errorOccurred);
                      return null;
                    }),
                () -> source.sendMessage(Settings.MESSAGES.wrongPassword),
                (e) -> source.sendMessage(Settings.MESSAGES.errorOccurred)
            );
          } else {
            source.sendMessage(Settings.MESSAGES.totpUsage);
          }
        } else if (args[0].equalsIgnoreCase("disable")) {
          if (args.length == 2) {
            this.dslContext.select(RegisteredPlayer.Table.TOTP_TOKEN_FIELD)
                .from(RegisteredPlayer.Table.INSTANCE)
                .where(DSL.field(RegisteredPlayer.Table.LOWERCASE_NICKNAME_FIELD).eq(lowercaseNickname))
                .fetchAsync()
                .thenAccept(totpTokenResult -> {
                  String totpCode;
                  if (totpTokenResult.isEmpty() || (totpCode = totpTokenResult.get(0).value1()).isEmpty()) {
                    source.sendMessage(Settings.MESSAGES.totpDisabled);
                    return;
                  }

                  if (AuthSessionHandler.getTotpCodeVerifier().isValidCode(totpCode, args[1])) {
                    this.dslContext.update(RegisteredPlayer.Table.INSTANCE)
                        .set(RegisteredPlayer.Table.TOTP_TOKEN_FIELD, "")
                        .where(DSL.field(RegisteredPlayer.Table.LOWERCASE_NICKNAME_FIELD).eq(username))
                        .executeAsync()
                        .thenRun(() -> source.sendMessage(Settings.MESSAGES.totpSuccessful))
                        .exceptionally(e -> {
                          this.plugin.handleSqlError(e);
                          source.sendMessage(Settings.MESSAGES.errorOccurred);
                          return null;
                        });
                  } else {
                    source.sendMessage(Settings.MESSAGES.totpWrong);
                  }
                })
                .exceptionally(e -> {
                  this.plugin.handleSqlError(e);
                  source.sendMessage(Settings.MESSAGES.errorOccurred);
                  return null;
                });
          } else {
            source.sendMessage(Settings.MESSAGES.totpUsage);
          }
        } else {
          source.sendMessage(Settings.MESSAGES.totpUsage);
        }
      }
    } else {
      source.sendMessage(Settings.MESSAGES.notPlayer);
    }
  }

  @Override
  public boolean hasPermission(SimpleCommand.Invocation invocation) {
    return Settings.IMP.commandPermissionState.totp.hasPermission(invocation.source(), "limboauth.commands.totp");
  }
}
