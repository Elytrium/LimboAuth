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
import java.text.MessageFormat;
import java.util.Locale;
import net.elytrium.commons.kyori.serialization.Serializer;
import net.elytrium.limboauth.LimboAuth;
import net.elytrium.limboauth.Settings;
import net.elytrium.limboauth.handler.AuthSessionHandler;
import net.elytrium.limboauth.model.RegisteredPlayer;
import net.kyori.adventure.text.Component;
import net.kyori.adventure.text.event.ClickEvent;
import org.jooq.DSLContext;
import org.jooq.impl.DSL;

public class TotpCommand implements SimpleCommand {

  private final SecretGenerator secretGenerator = new DefaultSecretGenerator();
  private final RecoveryCodeGenerator codesGenerator = new RecoveryCodeGenerator();
  private final LimboAuth plugin;
  private final Settings settings;
  private final DSLContext dslContext;

  private final Component notPlayer;
  private final Component usage;
  private final boolean needPassword;
  private final Component notRegistered;
  private final Component wrongPassword;
  private final Component alreadyEnabled;
  private final Component errorOccurred;
  private final Component successful;
  private final String issuer;
  private final String qrGeneratorUrl;
  private final Component qr;
  private final String token;
  private final int recoveryCodesAmount;
  private final String recovery;
  private final Component disabled;
  private final Component wrong;
  private final Component crackedCommand;

  public TotpCommand(LimboAuth plugin, Settings settings, DSLContext dslContext) {
    this.plugin = plugin;
    this.settings = settings;
    this.dslContext = dslContext;

    Serializer serializer = plugin.getSerializer();
    this.notPlayer = serializer.deserialize(this.settings.main.strings.NOT_PLAYER);
    this.usage = serializer.deserialize(this.settings.main.strings.TOTP_USAGE);
    this.needPassword = this.settings.main.totpNeedPassword;
    this.notRegistered = serializer.deserialize(this.settings.main.strings.NOT_REGISTERED);
    this.wrongPassword = serializer.deserialize(this.settings.main.strings.WRONG_PASSWORD);
    this.alreadyEnabled = serializer.deserialize(this.settings.main.strings.TOTP_ALREADY_ENABLED);
    this.errorOccurred = serializer.deserialize(this.settings.main.strings.ERROR_OCCURRED);
    this.successful = serializer.deserialize(this.settings.main.strings.TOTP_SUCCESSFUL);
    this.issuer = this.settings.main.totpIssuer;
    this.qrGeneratorUrl = this.settings.main.qrGeneratorUrl;
    this.qr = serializer.deserialize(this.settings.main.strings.TOTP_QR);
    this.token = this.settings.main.strings.TOTP_TOKEN;
    this.recoveryCodesAmount = this.settings.main.totpRecoveryCodesAmount;
    this.recovery = this.settings.main.strings.TOTP_RECOVERY;
    this.disabled = serializer.deserialize(this.settings.main.strings.TOTP_DISABLED);
    this.wrong = serializer.deserialize(this.settings.main.strings.TOTP_WRONG);
    this.crackedCommand = serializer.deserialize(this.settings.main.strings.CRACKED_COMMAND);
  }

  // TODO: Rewrite.
  @Override
  public void execute(SimpleCommand.Invocation invocation) {
    CommandSource source = invocation.source();
    String[] args = invocation.arguments();

    if (source instanceof Player) {
      if (args.length == 0) {
        source.sendMessage(this.usage);
      } else {
        String username = ((Player) source).getUsername();
        String lowercaseNickname = username.toLowerCase(Locale.ROOT);

        if (args[0].equalsIgnoreCase("enable")) {
          if (this.needPassword ? args.length == 2 : args.length == 1) {
            RegisteredPlayer.checkPassword(this.settings, this.dslContext, lowercaseNickname, this.needPassword ? args[1] : null,
                () -> source.sendMessage(this.notRegistered),
                () -> source.sendMessage(this.crackedCommand),
                h -> this.dslContext.selectFrom(RegisteredPlayer.Table.INSTANCE)
                    .where(DSL.field(RegisteredPlayer.Table.LOWERCASE_NICKNAME_FIELD).eq(username))
                    .fetchAsync()
                    .thenAccept(totpTokenResult -> {
                      if (totpTokenResult.isEmpty() || totpTokenResult.get(0).get(0, String.class).isEmpty()) {
                        String secret = this.secretGenerator.generate();
                        this.dslContext.update(RegisteredPlayer.Table.INSTANCE)
                            .set(RegisteredPlayer.Table.TOTP_TOKEN_FIELD, secret)
                            .where(DSL.field(RegisteredPlayer.Table.LOWERCASE_NICKNAME_FIELD).eq(username))
                            .executeAsync()
                            .thenRun(() -> source.sendMessage(this.successful))
                            .exceptionally(e -> {
                              this.plugin.handleSqlError(e);
                              source.sendMessage(this.errorOccurred);
                              return null;
                            });

                        QrData data = new QrData.Builder()
                            .label(username)
                            .secret(secret)
                            .issuer(this.issuer)
                            .build();
                        String qrUrl = this.qrGeneratorUrl.replace("{data}", URLEncoder.encode(data.getUri(), StandardCharsets.UTF_8));
                        source.sendMessage(this.qr.clickEvent(ClickEvent.openUrl(qrUrl)));

                        Serializer serializer = this.plugin.getSerializer();
                        source.sendMessage(serializer.deserialize(MessageFormat.format(this.token, secret))
                            .clickEvent(ClickEvent.copyToClipboard(secret)));
                        String codes = String.join(", ", this.codesGenerator.generateCodes(this.recoveryCodesAmount));
                        source.sendMessage(serializer.deserialize(MessageFormat.format(this.recovery, codes))
                            .clickEvent(ClickEvent.copyToClipboard(codes)));
                      } else {
                        source.sendMessage(this.alreadyEnabled);
                      }
                    })
                    .exceptionally(e -> {
                      this.plugin.handleSqlError(e);
                      source.sendMessage(this.errorOccurred);
                      return null;
                    }),
                () -> source.sendMessage(this.wrongPassword),
                (e) -> source.sendMessage(this.errorOccurred));
          } else {
            source.sendMessage(this.usage);
          }
        } else if (args[0].equalsIgnoreCase("disable")) {
          if (args.length == 2) {
            this.dslContext.select(RegisteredPlayer.Table.TOTP_TOKEN_FIELD)
                .from(RegisteredPlayer.Table.INSTANCE)
                .where(DSL.field(RegisteredPlayer.Table.LOWERCASE_NICKNAME_FIELD).eq(lowercaseNickname))
                .fetchAsync()
                .thenAccept(totpTokenResult -> {
                  String totpCode;
                  if (totpTokenResult.isEmpty() || (totpCode = totpTokenResult.get(0).get(0, String.class)).isEmpty()) {
                    source.sendMessage(this.disabled);
                    return;
                  }

                  if (AuthSessionHandler.getTotpCodeVerifier().isValidCode(totpCode, args[1])) {
                    this.dslContext.update(RegisteredPlayer.Table.INSTANCE)
                        .set(RegisteredPlayer.Table.TOTP_TOKEN_FIELD, "")
                        .where(DSL.field(RegisteredPlayer.Table.LOWERCASE_NICKNAME_FIELD).eq(username))
                        .executeAsync()
                        .thenRun(() -> source.sendMessage(this.successful))
                        .exceptionally(e -> {
                          this.plugin.handleSqlError(e);
                          source.sendMessage(this.errorOccurred);
                          return null;
                        });
                  } else {
                    source.sendMessage(this.wrong);
                  }
                })
                .exceptionally(e -> {
                  this.plugin.handleSqlError(e);
                  source.sendMessage(this.errorOccurred);
                  return null;
                });
          } else {
            source.sendMessage(this.usage);
          }
        } else {
          source.sendMessage(this.usage);
        }
      }
    } else {
      source.sendMessage(this.notPlayer);
    }
  }

  @Override
  public boolean hasPermission(SimpleCommand.Invocation invocation) {
    return this.settings.main.commandPermissionState.totp
        .hasPermission(invocation.source(), "limboauth.commands.totp");
  }
}
