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

import com.velocitypowered.api.proxy.Player;
import java.util.List;
import java.util.Locale;
import java.util.function.Consumer;
import java.util.random.RandomGenerator;
import net.elytrium.limboauth.LimboAuth;
import net.elytrium.limboauth.Settings;
import net.elytrium.limboauth.api.events.ChangePasswordEvent;
import net.elytrium.limboauth.data.Database;
import net.elytrium.limboauth.data.PlayerData;
import net.elytrium.limboauth.utils.Random;
import net.elytrium.limboauth.utils.command.Arguments;
import net.elytrium.limboauth.utils.command.Commands;
import net.elytrium.limboauth.utils.command.Suggestions;

public class ChangePasswordCommand {

  private static final char[] TABLE = {
      '+', '-', '.', '0', '1', '2', '3', '4', '5', '6', '7', '8', '9',
      'A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M',
      'N', 'O', 'P', 'Q', 'R', 'S', 'T', 'U', 'V', 'W', 'X', 'Y', 'Z',
      '_',
      'a', 'b', 'c', 'd', 'e', 'f', 'g', 'h', 'i', 'j', 'k', 'l', 'm',
      'n', 'o', 'p', 'q', 'r', 's', 't', 'u', 'v', 'w', 'x', 'y', 'z'
  };

  public static void init(LimboAuth plugin) {
    plugin.registerCommand(List.of("changepassword", "changepass", "cp"), command -> {
      command.requires(Commands.requirePermission(Settings.PERMISSION_STATES.changePassword, "limboauth.commands.changepassword"))
          .executes(Commands.executePlayer(ChangePasswordCommand::execute));
      String first = Settings.MESSAGES.changePasswordFirstArgumentName;
      var suggestionProvider = Suggestions.suggestExactly(sender -> {
        if (sender instanceof Player player) {
          RandomGenerator random = Random.get(player.getRemoteAddress().getAddress().getHostAddress().hashCode());
          char[] buf = new char[16];
          for (int i = 0; i < 16; ++i) {
            buf[i] = ChangePasswordCommand.TABLE[random.nextInt(ChangePasswordCommand.TABLE.length)];
          }

          return new String(buf);
        }

        return null;
      });
      if (Settings.HEAD.changePasswordNeedOldPassword) {
        String second = Settings.MESSAGES.changePasswordSecondArgumentName;
        command.then(Arguments.word(first)
            .requires(sender -> sender instanceof Player player && !player.isOnlineMode()) // Online players should not run this sub commands because they don't have an old password
            .executes(Commands.executePlayer(ChangePasswordCommand::execute))
            .then(Arguments.word(second)
                .suggests(suggestionProvider)
                .executes(Commands.executePlayer((context, player) -> ChangePasswordCommand.execute(plugin, player, Arguments.getString(context, first), Arguments.getString(context, second))))
            )
        ).then(Arguments.word(second)
            .requires(sender -> sender instanceof Player player && player.isOnlineMode())
            .suggests(suggestionProvider)
            .executes(Commands.executePlayer((context, player) -> ChangePasswordCommand.execute(plugin, player, null, Arguments.getString(context, second))))
        );
      } else {
        command.then(Arguments.word(first)
            .suggests(suggestionProvider)
            .executes(Commands.executePlayer((context, player) -> ChangePasswordCommand.execute(plugin, player, null, Arguments.getString(context, first))))
        );
      }
    });
  }

  private static void execute(Player player) {
    player.sendMessage(Settings.MESSAGES.changePasswordUsage);
  }

  private static void execute(LimboAuth plugin, Player player, String oldPassword, String newPassword) {
    String username = player.getUsername();
    String lowercaseNickname = username.toLowerCase(Locale.ROOT);
    Consumer<String> onCorrect = oldHash -> {
      final String newHash = PlayerData.genHash(newPassword);

      Database.get().update(PlayerData.Table.INSTANCE)
          .set(PlayerData.Table.HASH_FIELD, newHash)
          .where(PlayerData.Table.NICKNAME_FIELD.eq(username))
          .executeAsync();

      plugin.getCacheManager().removePlayerFromCache(username);

      plugin.getServer().getEventManager().fireAndForget(new ChangePasswordEvent(username, oldPassword, oldHash, newPassword, newHash));

      player.sendMessage(Settings.MESSAGES.changePasswordSuccessful);
    };

    /* TODO
    PlayerData.checkPassword(lowercaseNickname, needOldPass ? args[0] : null,
        () -> source.sendMessage(Settings.MESSAGES.notRegistered),
        () -> onCorrect.accept(null),
        onCorrect,
        () -> source.sendMessage(Settings.MESSAGES.wrongPassword),
        e -> source.sendMessage(Settings.MESSAGES.errorOccurred)
    );
    */
  }
}
