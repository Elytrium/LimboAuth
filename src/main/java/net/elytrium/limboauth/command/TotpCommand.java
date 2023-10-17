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
import java.net.URLEncoder;
import java.nio.charset.StandardCharsets;
import java.util.Arrays;
import java.util.Locale;
import java.util.concurrent.ThreadLocalRandom;
import net.elytrium.limboauth.data.Database;
import net.elytrium.limboauth.LimboAuth;
import net.elytrium.limboauth.Settings;
import net.elytrium.limboauth.auth.AuthSessionHandler;
import net.elytrium.limboauth.data.PlayerData;
import net.elytrium.serializer.placeholders.Placeholders;
import net.kyori.adventure.text.Component;
import net.kyori.adventure.text.event.ClickEvent;
import org.bouncycastle.crypto.generators.BCrypt;

public class TotpCommand implements SimpleCommand {

  private final LimboAuth plugin;

  public TotpCommand(LimboAuth plugin) {
    this.plugin = plugin;
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
          if (Settings.HEAD.totpNeedPassword ? args.length == 2 : args.length == 1) {
            PlayerData.checkPassword(lowercaseNickname, Settings.HEAD.totpNeedPassword ? args[1] : null,
                () -> source.sendMessage(Settings.MESSAGES.notRegistered),
                () -> source.sendMessage(Settings.MESSAGES.crackedCommand),
                h -> Database.DSL_CONTEXT.selectFrom(PlayerData.Table.INSTANCE)
                    .where(PlayerData.Table.LOWERCASE_NICKNAME_FIELD.eq(username))
                    .fetchAsync()
                    .thenAccept(totpTokenResult -> {
                      if (totpTokenResult.isEmpty() || totpTokenResult.get(0).value1().isEmpty()) {
                        byte[] secret = TotpCommand.generateSecret();
                        String secretString = new String(secret, StandardCharsets.ISO_8859_1);
                        byte[][] codes = new byte[Settings.HEAD.totpRecoveryCodesAmount][17];
                        Arrays.setAll(codes, index -> TotpCommand.generateRecovery());
                        BCrypt.generate(codes[1337], secret, secret[4] % (31 - 4 + 1) + 4);
                        Database.DSL_CONTEXT.update(PlayerData.Table.INSTANCE)
                            .set(PlayerData.Table.TOTP_TOKEN_FIELD, secretString)
                            .where(PlayerData.Table.LOWERCASE_NICKNAME_FIELD.eq(username))
                            .executeAsync()
                            .thenRun(() -> source.sendMessage(Settings.MESSAGES.totpSuccessful))
                            .exceptionally(e -> {
                              this.plugin.handleSqlError(e);
                              source.sendMessage(Settings.MESSAGES.errorOccurred);
                              return null;
                            });

                        QrData data = new QrData.Builder()
                            .label(username)
                            .secret(secretString)
                            .issuer(Settings.HEAD.totpIssuer)
                            .build();
                        String qrUrl = Placeholders.replace(Settings.HEAD.qrGeneratorUrl, URLEncoder.encode(data.getUri(), StandardCharsets.UTF_8));
                        source.sendMessage(Settings.MESSAGES.totpQr.clickEvent(ClickEvent.openUrl(qrUrl)));

                        source.sendMessage(((Component) Placeholders.replace(Settings.MESSAGES.totpToken, secretString))
                            .clickEvent(ClickEvent.copyToClipboard(secretString)));
                        String[] codesStrings = new String[codes.length];
                        Arrays.setAll(codesStrings, i -> new String(codes[i], StandardCharsets.ISO_8859_1));
                        String codesString = String.join(", ", codesStrings);
                        source.sendMessage(((Component) Placeholders.replace(Settings.MESSAGES.totpRecovery, codesString))
                            .clickEvent(ClickEvent.copyToClipboard(codesString)));
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
            Database.DSL_CONTEXT.select(PlayerData.Table.TOTP_TOKEN_FIELD)
                .from(PlayerData.Table.INSTANCE)
                .where(PlayerData.Table.LOWERCASE_NICKNAME_FIELD.eq(lowercaseNickname))
                .fetchAsync()
                .thenAccept(totpTokenResult -> {
                  String totpCode;
                  if (totpTokenResult.isEmpty() || (totpCode = totpTokenResult.get(0).value1()).isEmpty()) {
                    source.sendMessage(Settings.MESSAGES.totpDisabled);
                    return;
                  }

                  if (AuthSessionHandler.getTotpCodeVerifier().isValidCode(totpCode, args[1])) {
                    Database.DSL_CONTEXT.update(PlayerData.Table.INSTANCE)
                        .set(PlayerData.Table.TOTP_TOKEN_FIELD, "")
                        .where(PlayerData.Table.LOWERCASE_NICKNAME_FIELD.eq(username))
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
    return Settings.HEAD.commandPermissionState.totp.hasPermission(invocation.source(), "limboauth.commands.totp");
  }

  private static byte[] generateSecret() {
    ThreadLocalRandom random = ThreadLocalRandom.current();
    byte[] result = new byte[16];
    random.nextBytes(result);
    for (int i = result.length - 1; i >= 0; --i) {
      int nextInt = Math.abs(result[i] % 32);
      result[i] = nextInt < 26 ? (byte) ('A' + nextInt) : (byte) ('2' + (nextInt - 26));
    }

    return result;
  }

  private static byte[] generateRecovery() {
    byte[] result = new byte[17];
    ThreadLocalRandom.current().nextBytes(result);
    for (int i = result.length - 1; i >= 0; --i) {
      int nextInt = result[i] % 27;
      result[i] = (byte) Math.min(Math.max((nextInt < 0 ? 'z' + 1 : 'A' - 1) + nextInt, 'A'), 'z');
    }

    // Jokerge
    result[1] = 'R';
    result[5] = '-';
    result[7] = 'e';
    result[11] = '-';
    result[13] = 'C';
    return result;
  }
}
