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

package net.elytrium.limboauth;

import com.google.inject.Inject;
import com.google.inject.name.Named;
import com.velocitypowered.api.command.CommandManager;
import com.velocitypowered.api.event.EventManager;
import com.velocitypowered.api.event.Subscribe;
import com.velocitypowered.api.event.proxy.ProxyInitializeEvent;
import com.velocitypowered.api.event.proxy.ProxyShutdownEvent;
import com.velocitypowered.api.plugin.Dependency;
import com.velocitypowered.api.plugin.Plugin;
import com.velocitypowered.api.plugin.PluginContainer;
import com.velocitypowered.api.plugin.PluginManager;
import com.velocitypowered.api.plugin.annotation.DataDirectory;
import com.velocitypowered.api.proxy.ProxyServer;
import java.nio.file.Path;
import java.util.concurrent.ExecutorService;
import net.elytrium.limboauth.utils.LibrariesLoader;
import org.bstats.velocity.Metrics;
import org.slf4j.Logger;

@Plugin(
    id = "limboauth",
    name = "LimboAuth",
    version = BuildConfig.VERSION,
    url = "https://elytrium.net/",
    authors = {
        "Elytrium (https://elytrium.net/)",
    },
    dependencies = {
        @Dependency(id = "limboapi"),
        @Dependency(id = "floodgate", optional = true)
    }
)
public class Bootstrap { // в идеале этот класс не нужен и подгрузка либ должна быть в основном классе, чек комменты в нём

  private final LimboAuth limboAuth;

  @Inject
  public Bootstrap(Logger logger, @DataDirectory Path dataDirectory, ProxyServer server, Metrics.Factory metricsFactory, ExecutorService executor, @Named("limboapi") PluginContainer limboApi) {
    this.limboAuth = new LimboAuth(logger, dataDirectory, server, metricsFactory, executor, limboApi);
  }

  @Subscribe
  public void onProxyInitialization(ProxyInitializeEvent event) throws Throwable {
    LibrariesLoader.resolveAndLoad(this, this.limboAuth.getLogger(), this.limboAuth.getServer(), BuildConfig.COMMON_DEPENDENCIES);
    this.limboAuth.onProxyInitialization();
  }

  @Subscribe
  public void onProxyShutdown(ProxyShutdownEvent event) {
    this.limboAuth.onProxyShutdown();
  }
}
