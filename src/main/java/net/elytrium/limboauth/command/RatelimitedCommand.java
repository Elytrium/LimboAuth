/*
 * Copyright (C) 2021 - 2024 Elytrium
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
import net.elytrium.limboauth.LimboAuth;
import net.elytrium.limboauth.Settings;
import net.kyori.adventure.text.Component;

public abstract class RatelimitedCommand implements SimpleCommand {

  private final Component ratelimited;

  public RatelimitedCommand() {
    this.ratelimited = LimboAuth.getSerializer().deserialize(Settings.IMP.MAIN.STRINGS.RATELIMITED);
  }

  @Override
  public final void execute(SimpleCommand.Invocation invocation) {
    CommandSource source = invocation.source();
    if (source instanceof Player) {
      if (!LimboAuth.getRatelimiter().attempt(((Player) source).getRemoteAddress().getAddress())) {
        source.sendMessage(this.ratelimited);
        return;
      }
    }

    this.execute(source, invocation.arguments());
  }

  protected abstract void execute(CommandSource source, String[] args);
}
