/*
 * Copyright (C) 2021 - 2025 Elytrium
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
import net.elytrium.limboauth.LimboAuth;
import net.elytrium.limboauth.Settings;
import net.kyori.adventure.text.Component;
import org.jetbrains.annotations.NotNull;

public abstract class TaskEvent {

  private static Component DEFAULT_REASON;

  private final Consumer<TaskEvent> onComplete;

  private Result result = Result.NORMAL;
  private Component reason = DEFAULT_REASON;

  public TaskEvent(Consumer<TaskEvent> onComplete) {
    this.onComplete = onComplete;
  }

  public TaskEvent(Consumer<TaskEvent> onComplete, Result result) {
    this.onComplete = onComplete;
    this.result = result;
  }

  public void complete(@NotNull Result result) {
    if (this.result != Result.WAIT) {
      return;
    }

    this.result = result;
    this.onComplete.accept(this);
  }

  public void completeAndCancel(@NotNull Component reason) {
    if (this.result != Result.WAIT) {
      return;
    }

    this.cancel(reason);
    this.onComplete.accept(this);
  }

  public void cancel(@NotNull Component reason) {
    this.result = Result.CANCEL;
    this.reason = reason;
  }

  public void setResult(@NotNull Result result) {
    this.result = result;
  }

  public Result getResult() {
    return this.result;
  }

  public Component getReason() {
    return this.reason;
  }

  public static void reload() {
    DEFAULT_REASON = LimboAuth.getSerializer().deserialize(Settings.IMP.MAIN.STRINGS.EVENT_CANCELLED);
  }

  public enum Result {

    CANCEL,
    BYPASS,
    NORMAL,
    WAIT
  }
}
