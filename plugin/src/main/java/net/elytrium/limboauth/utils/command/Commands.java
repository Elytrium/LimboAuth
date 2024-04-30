/*
 * Copyright (C) 2024 Elytrium
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

package net.elytrium.limboauth.utils.command;

import com.mojang.brigadier.Command;
import com.mojang.brigadier.LiteralMessage;
import com.mojang.brigadier.builder.LiteralArgumentBuilder;
import com.mojang.brigadier.context.CommandContext;
import com.mojang.brigadier.exceptions.CommandSyntaxException;
import com.mojang.brigadier.exceptions.SimpleCommandExceptionType;
import com.velocitypowered.api.command.CommandSource;
import com.velocitypowered.api.permission.PermissionSubject;
import com.velocitypowered.api.permission.Tristate;
import com.velocitypowered.api.proxy.Player;
import java.util.function.Predicate;
import net.elytrium.limboauth.LimboAuth;

public class Commands {

  private static final SimpleCommandExceptionType ERROR_NOT_PLAYER = new SimpleCommandExceptionType(new LiteralMessage("A player is required to run this command here"));

  public static LiteralArgumentBuilder<CommandSource> sub(String command) {
    return LiteralArgumentBuilder.literal(command);
  }

  public static Predicate<CommandSource> requirePermission(PermissionState state, String permission) {
    return source -> state.hasPermission(source, permission);
  }

  public static Command<CommandSource> execute(CommandExecutor<CommandSource> executor) {
    return context -> {
      try {
        executor.execute(context.getSource());
      } catch (CommandSyntaxException e) {
        throw e;
      } catch (Exception e) {
        LimboAuth.LOGGER.error(context.getInput(), e);
      }

      return Command.SINGLE_SUCCESS;
    };
  }

  public static Command<CommandSource> execute(CommandContextExecutor<CommandSource> executor) {
    return context -> {
      try {
        executor.execute(context, context.getSource());
      } catch (CommandSyntaxException e) {
        throw e;
      } catch (Exception e) {
        LimboAuth.LOGGER.error(context.getInput(), e);
      }

      return Command.SINGLE_SUCCESS;
    };
  }

  public static Command<CommandSource> executePlayer(CommandExecutor<Player> executor) {
    return context -> {
      try {
        CommandSource source = context.getSource();
        if (source instanceof Player player) {
          executor.execute(player);
        } else {
          throw Commands.ERROR_NOT_PLAYER.create();
        }
      } catch (CommandSyntaxException e) {
        throw e;
      } catch (Exception e) {
        LimboAuth.LOGGER.error(context.getInput(), e);
      }

      return Command.SINGLE_SUCCESS;
    };
  }

  public static Command<CommandSource> executePlayer(CommandContextExecutor<Player> executor) {
    return context -> {
      try {
        CommandSource source = context.getSource();
        if (source instanceof Player player) {
          executor.execute(context, player);
        } else {
          throw Commands.ERROR_NOT_PLAYER.create();
        }
      } catch (CommandSyntaxException e) {
        throw e;
      } catch (Exception e) {
        LimboAuth.LOGGER.error(context.getInput(), e);
      }

      return Command.SINGLE_SUCCESS;
    };
  }

  public enum PermissionState {

    FALSE {
      @Override
      public boolean hasPermission(PermissionSubject subject, String permission) {
        return false;
      }
    },
    TRUE {
      @Override
      public boolean hasPermission(PermissionSubject subject, String permission) {
        return subject.getPermissionValue(permission) != Tristate.FALSE;
      }
    },
    PERMISSION {
      @Override
      public boolean hasPermission(PermissionSubject subject, String permission) {
        return subject.hasPermission(permission);
      }
    };

    public abstract boolean hasPermission(PermissionSubject subject, String permission);
  }

  @FunctionalInterface
  public interface CommandExecutor<S> {

    void execute(S sender) throws CommandSyntaxException;
  }

  @FunctionalInterface
  public interface CommandContextExecutor<S> {

    void execute(CommandContext<CommandSource> context, S sender) throws CommandSyntaxException;
  }
}
