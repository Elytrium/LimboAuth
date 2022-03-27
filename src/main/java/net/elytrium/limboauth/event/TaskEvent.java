/*
 * Copyright (C) 2021 Elytrium
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

package net.elytrium.limboauth.event;

import java.util.function.Consumer;
import net.elytrium.limboauth.Settings;
import net.kyori.adventure.text.Component;
import net.kyori.adventure.text.serializer.legacy.LegacyComponentSerializer;
import org.jetbrains.annotations.NotNull;

public abstract class TaskEvent {
  private static final Component defaultReason
      = LegacyComponentSerializer.legacyAmpersand().deserialize(Settings.IMP.MAIN.STRINGS.EVENT_CANCELLED);
  private Result result = Result.NORMAL;
  private Component reason = defaultReason;

  private final Consumer<TaskEvent> onComplete;

  public TaskEvent(Consumer<TaskEvent> onComplete) {
    this.onComplete = onComplete;
  }

  public Result getResult() {
    return this.result;
  }

  public void setResult(@NotNull Result result) {
    this.result = result;
  }

  public void cancel(@NotNull Component reason) {
    this.result = Result.CANCEL;
    this.reason = reason;
  }

  public Component getReason() {
    return this.reason;
  }

  public void complete(Result result) {
    if (this.result != Result.WAIT) {
      return;
    }

    this.result = result;
    this.onComplete.accept(this);
  }

  public void completeAndCancel(@NotNull Component c) {
    if (this.result != Result.WAIT) {
      return;
    }

    this.cancel(c);
    this.onComplete.accept(this);
  }

  public enum Result {
    CANCEL,
    BYPASS,
    NORMAL,
    WAIT
  }
}
