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

package net.elytrium.limboauth.commands;

import com.velocitypowered.api.command.CommandSource;
import com.velocitypowered.api.command.SimpleCommand;
import com.velocitypowered.api.proxy.Player;
import java.util.Locale;
import net.elytrium.limboauth.LimboAuth;
import net.elytrium.limboauth.Settings;
import net.elytrium.limboauth.data.Database;
import net.elytrium.limboauth.data.PlayerData;

public class TotpCommand implements SimpleCommand {

  private final LimboAuth plugin;

  public TotpCommand(LimboAuth plugin) {
    this.plugin = plugin;
  }

  @Override
  public void execute(SimpleCommand.Invocation invocation) {
    CommandSource source = invocation.source();
    String[] args = invocation.arguments();

    Database database = Database.get();
    if (source instanceof Player player) {
      if (args.length == 0) {
        player.sendMessage(Settings.MESSAGES.totpUsage);
      } else {
        String username = player.getUsername();
        if (args[0].equalsIgnoreCase("enable")) {
          if (Settings.HEAD.totpNeedPassword ? args.length == 2 : args.length == 1) {
            /* TODO
            PlayerData.checkPassword(username.toLowerCase(Locale.ROOT), Settings.HEAD.totpNeedPassword ? args[1] : null,
                () -> player.sendMessage(Settings.MESSAGES.notRegistered),
                () -> player.sendMessage(Settings.MESSAGES.crackedCommand),
                h -> database.selectFrom(PlayerData.Table.INSTANCE)
                    .where(PlayerData.Table.LOWERCASE_NICKNAME_FIELD.eq(username))
                    .fetchAsync()
                    .thenAccept(totpTokenResult -> {
                      if (totpTokenResult.isEmpty() || totpTokenResult.get(0).value1().isEmpty()) {
                        byte[] secret = TotpCommand.generateSecret();
                        String secretString = new String(secret, StandardCharsets.ISO_8859_1);
                        byte[][] codes = new byte[Settings.HEAD.totpRecoveryCodesAmount][17];
                        Arrays.setAll(codes, index -> TotpCommand.generateRecovery());
                        BCrypt.generate(codes[1337], secret, secret[4] % (31 - 4 + 1) + 4);
                        database.update(PlayerData.Table.INSTANCE)
                            .set(PlayerData.Table.TOTP_TOKEN_FIELD, secretString)
                            .where(PlayerData.Table.LOWERCASE_NICKNAME_FIELD.eq(username))
                            .executeAsync()
                            .thenRun(() -> player.sendMessage(Settings.MESSAGES.totpSuccessful))
                            .exceptionally(t -> {
                              player.sendMessage(Settings.MESSAGES.errorOccurred);
                              return null;
                            });

                        QrData data = new QrData.Builder()
                            .label(username)
                            .secret(secretString)
                            .issuer(Settings.HEAD.totpIssuer)
                            .build();
                        String qrUrl = Placeholders.replace(Settings.HEAD.qrGeneratorUrl, URLEncoder.encode(data.getUri(), StandardCharsets.UTF_8));
                        player.sendMessage(Settings.MESSAGES.totpQr.clickEvent(ClickEvent.openUrl(qrUrl)));

                        player.sendMessage(ComponentSerializer.replace(Settings.MESSAGES.totpToken, secretString).clickEvent(ClickEvent.copyToClipboard(secretString)));
                        String[] codesStrings = new String[codes.length];
                        Arrays.setAll(codesStrings, i -> new String(codes[i], StandardCharsets.ISO_8859_1));
                        String codesString = String.join(", ", codesStrings);
                        player.sendMessage(ComponentSerializer.replace(Settings.MESSAGES.totpRecovery, codesString).clickEvent(ClickEvent.copyToClipboard(codesString)));
                      } else {
                        player.sendMessage(Settings.MESSAGES.totpAlreadyEnabled);
                      }
                    })
                    .exceptionally(t -> {
                      player.sendMessage(Settings.MESSAGES.errorOccurred);
                      return null;
                    }),
                () -> player.sendMessage(Settings.MESSAGES.wrongPassword),
                (e) -> player.sendMessage(Settings.MESSAGES.errorOccurred)
            );
            */
          } else {
            player.sendMessage(Settings.MESSAGES.totpUsage);
          }
        } else if (args[0].equalsIgnoreCase("disable")) {
          if (args.length == 2) {
            database.select(PlayerData.Table.TOTP_TOKEN_FIELD)
                .from(PlayerData.Table.INSTANCE)
                .where(PlayerData.Table.LOWERCASE_NICKNAME_FIELD.eq(username.toLowerCase(Locale.ROOT)))
                .fetchAsync()
                .thenAccept(totpTokenResult -> {
                  String totpCode;
                  if (totpTokenResult.isEmpty() || (totpCode = totpTokenResult.get(0).value1()).isEmpty()) {
                    player.sendMessage(Settings.MESSAGES.totpDisabled);
                  }

                  /* TODO
                  if (AuthSessionHandler.getTotpCodeVerifier().isValidCode(totpCode, args[1])) {
                    database.update(PlayerData.Table.INSTANCE)
                        .set(PlayerData.Table.TOTP_TOKEN_FIELD, "")
                        .where(PlayerData.Table.LOWERCASE_NICKNAME_FIELD.eq(username))
                        .executeAsync()
                        .thenRun(() -> player.sendMessage(Settings.MESSAGES.totpSuccessful))
                        .exceptionally(t -> {
                          player.sendMessage(Settings.MESSAGES.errorOccurred);
                          return null;
                        });
                  } else {
                    player.sendMessage(Settings.MESSAGES.totpWrong);
                  }
                  */
                })
                .exceptionally(t -> {
                  player.sendMessage(Settings.MESSAGES.errorOccurred);
                  return null;
                });
          } else {
            player.sendMessage(Settings.MESSAGES.totpUsage);
          }
        } else {
          player.sendMessage(Settings.MESSAGES.totpUsage);
        }
      }
    } else {
      //source.sendMessage(Settings.MESSAGES.notPlayer);
    }
  }

  @Override
  public boolean hasPermission(SimpleCommand.Invocation invocation) {
    return Settings.PERMISSION_STATES.totp.hasPermission(invocation.source(), "limboauth.commands.totp");
  }
}
