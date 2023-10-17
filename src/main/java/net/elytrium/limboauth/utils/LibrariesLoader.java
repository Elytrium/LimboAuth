package net.elytrium.limboauth.utils;

import com.velocitypowered.api.proxy.ProxyServer;
import java.io.IOException;
import java.io.InputStream;
import java.net.URL;
import java.nio.file.Files;
import java.nio.file.Path;
import java.nio.file.StandardCopyOption;
import java.util.Arrays;
import org.slf4j.Logger;

public class LibrariesLoader {

  private static final long TWO_WEEKS_MILLIS = 2/*weeks*/ * 7/*days*/ * 24/*hours*/ * 60/*minutes*/ * 60/*seconds*/ * 1000/*millis*/;

  private static final int SHA1_LENGTH_IN_HEX = 20 << 1;

  private static boolean fastutil_loaded;

  public static void resolveAndLoad(Object plugin, Logger logger, ProxyServer server, String[] libraries) throws Throwable { // TODO versioning, custom repo
    Path librariesDirectory = Path.of("libraries");
    for (int i = libraries.length - 1; i >= 0; --i) {
      String library = libraries[i];
      // fool-proof
      if (library == null) {
        return;
      }
      libraries[i] = null;

      final boolean fastutil = library.startsWith("it/unimi/dsi/fastutil");
      if (fastutil && library.contains("-core")) {
        logger.warn("Plugin tried to load the base version of fastutil, if you are the author of this plugin, then please use the full version (not -base).");
        continue;
      }

      final Path jarPath = librariesDirectory.resolve(library);
      final String jarUrl = "https://repo.maven.apache.org/maven2/" + library;

      final Path sha1Path = librariesDirectory.resolve(library + ".sha1");
      final String sha1Url = "https://repo.maven.apache.org/maven2/" + library + ".sha1";

      byte[] expectedHash;
      if (Files.exists(sha1Path) && System.currentTimeMillis() - sha1Path.toFile().lastModified() < LibrariesLoader.TWO_WEEKS_MILLIS) {
        expectedHash = Files.readAllBytes(sha1Path);
      } else {
        Files.createDirectories(jarPath.getParent());
        logger.info("Fetching {}", sha1Url);
        try (InputStream inputStream = new URL(sha1Url).openStream()) {
          expectedHash = inputStream.readNBytes(LibrariesLoader.SHA1_LENGTH_IN_HEX);
          Files.write(sha1Path, expectedHash);
        } catch (Throwable t) {
          logger.info("Unable to fetch {}", sha1Url);
          expectedHash = null;
        }
      }

      if (!Files.exists(jarPath) || LibrariesLoader.notMatches(expectedHash, jarPath)) {
        logger.info("Downloading {}", jarUrl);
        int attempt = 0;
        do {
          if (attempt == 5) {
            logger.info("Download failed after 5 times, shutting down the server.");
            server.shutdown();
            return;
          } else if (attempt != 0) {
            logger.info("Failed to download, trying again.");
          }

          try (InputStream inputStream = new URL(jarUrl).openStream()) {
            Files.copy(inputStream, jarPath, StandardCopyOption.REPLACE_EXISTING);
          } catch (Throwable t) {
            logger.info(t.getMessage(), t);
          }

          ++attempt;
        } while (LibrariesLoader.notMatches(expectedHash, jarPath));
      }

      if (fastutil) {
        // We're doing this because velocity already shades some fastutil classes, but does it incompletely.
        if (!LibrariesLoader.fastutil_loaded) {
          ClassLoader classLoader = server.getClass().getClassLoader();
          Reflection.void1(classLoader.getClass(), "appendToClassPathForInstrumentation", String.class).invoke(classLoader, jarPath.toString());
          LibrariesLoader.fastutil_loaded = true;
        }
      } else {
        server.getPluginManager().addToClasspath(plugin, jarPath);
      }

      logger.info("Loaded library {}", jarPath.toAbsolutePath());
    }
  }

  private static boolean notMatches(byte[] expectedHash, Path jarPath) throws IOException {
    return expectedHash != null && !Arrays.equals(expectedHash, Hex.encode2Bytes(Hashing.sha1(Files.readAllBytes(jarPath))));
  }
}
