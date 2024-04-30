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

package net.elytrium.limboauth;

import com.google.inject.Inject;
import com.google.inject.name.Named;
import com.velocitypowered.api.plugin.Dependency;
import com.velocitypowered.api.plugin.Plugin;
import com.velocitypowered.api.plugin.PluginContainer;
import com.velocitypowered.api.plugin.PluginManager;
import com.velocitypowered.api.plugin.annotation.DataDirectory;
import com.velocitypowered.api.proxy.ProxyServer;
import java.io.IOException;
import java.io.InputStream;
import java.lang.reflect.InvocationTargetException;
import java.net.MalformedURLException;
import java.net.URL;
import java.net.URLClassLoader;
import java.nio.file.Files;
import java.nio.file.Path;
import java.nio.file.StandardCopyOption;
import java.util.Map;
import java.util.concurrent.ExecutorService;
import org.slf4j.LoggerFactory;

@Plugin(
    id = Bootstrap.PLUGIN_ID,
    name = "LimboAuth",
    version = BuildConfig.VERSION,
    url = "https://elytrium.net/",
    authors = {
        "Elytrium",
    },
    dependencies = {
        @Dependency(id = "limboapi"),
        @Dependency(id = "floodgate", optional = true)
    }
)
public final class Bootstrap {

  static final String PLUGIN_ID = "limboauth";
  private static final String MAIN_CLASS = "net.elytrium.limboauth.LimboAuth";

  @Inject
  @SuppressWarnings("unchecked")
  private Bootstrap(@Named(Bootstrap.PLUGIN_ID) PluginContainer self, @DataDirectory Path dataDirectory, ProxyServer server, ExecutorService executor, @Named("limboapi") PluginContainer limboApi)
      throws IOException, ClassNotFoundException, NoSuchMethodException, InstantiationException, IllegalAccessException, InvocationTargetException {
    // Inspired luckperms' BukkitLoaderPlugin and JarInJarClassLoader
    // https://github.com/LuckPerms/LuckPerms/blob/f86585c3ccc7fe74fbb3b1d0251d93f263d70fe1/bukkit/loader/src/main/java/me/lucko/luckperms/bukkit/loader/BukkitLoaderPlugin.java
    // https://github.com/LuckPerms/LuckPerms/blob/f86585c3ccc7fe74fbb3b1d0251d93f263d70fe1/common/loader-utils/src/main/java/me/lucko/luckperms/common/loader/JarInJarClassLoader.java
    Path file;
    try (InputStream plugin = Bootstrap.class.getResourceAsStream("/plugin.jar")) {
      if (plugin == null) {
        LoggerFactory.getLogger(Bootstrap.PLUGIN_ID).error("Could not find inner plugin.jar.");
        return;
      }

      file = Files.createTempFile(Bootstrap.PLUGIN_ID + "-", ".jar.tmp");
      file.toFile().deleteOnExit();
      Files.copy(plugin, file, StandardCopyOption.REPLACE_EXISTING);
    }

    Loader loader = new Loader(file);
    var constructor = loader.loadClass(Bootstrap.MAIN_CLASS).getDeclaredConstructor(URLClassLoader.class, Path.class, ProxyServer.class, ExecutorService.class, PluginContainer.class);
    constructor.setAccessible(true);
    Object instance = constructor.newInstance(loader, dataDirectory, server, executor, limboApi);

    executor.execute(() -> {
      // We can't do it immediately because of VelocityPluginContainer#setInstance, which is called right after constructor call, so delay it a bit
      try {
        // Note: setInstance is public, and we can call it without reflection, but I think it’s not worth to pull in velocity-proxy once again
        self.getClass().getMethod("setInstance", Object.class).invoke(self, instance);

        PluginManager pluginManager = server.getPluginManager();
        var clazz = pluginManager.getClass();

        var pluginsById = clazz.getDeclaredField("pluginsById");
        pluginsById.setAccessible(true);
        ((Map<String, PluginContainer>) pluginsById.get(pluginManager)).put(Bootstrap.PLUGIN_ID, self);

        var pluginInstances = clazz.getDeclaredField("pluginInstances");
        pluginInstances.setAccessible(true);
        ((Map<Object, PluginContainer>) pluginInstances.get(pluginManager)).put(instance, self);
      } catch (NoSuchMethodException | InvocationTargetException | NoSuchFieldException | IllegalAccessException e) {
        throw new RuntimeException(e);
      }
    });
  }

  public static class Loader extends URLClassLoader {

    private final Path plugin;

    public Loader(Path plugin) throws MalformedURLException {
      super(new URL[] {
          plugin.toUri().toURL()
      }, Bootstrap.class.getClassLoader());
      this.plugin = plugin;
    }

    @Override
    public void close() throws IOException {
      super.close();
      Files.deleteIfExists(this.plugin);
    }

    @SuppressWarnings("unused")
    public void addPath(Path path) throws MalformedURLException {
      super.addURL(path.toUri().toURL());
    }

    static {
      ClassLoader.registerAsParallelCapable();
    }
  }
}
