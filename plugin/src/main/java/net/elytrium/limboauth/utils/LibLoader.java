/*
 * Copyright (C) 2023-2024 Elytrium
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

package net.elytrium.limboauth.utils;

import com.velocitypowered.api.proxy.ProxyServer;
import java.io.FileNotFoundException;
import java.io.IOException;
import java.io.InputStream;
import java.lang.invoke.MethodHandle;
import java.net.URL;
import java.nio.file.Files;
import java.nio.file.Path;
import java.nio.file.StandardCopyOption;
import java.util.Arrays;
import org.slf4j.Logger;

public class LibLoader {

  private static final int MAX_ATTEMPTS = 3;

  private static final long TWO_WEEKS_MILLIS = 2/*weeks*/ * 7/*days*/ * 24/*hours*/ * 60/*minutes*/ * 60/*seconds*/ * 1000/*millis*/;
  private static final int SHA1_HEX_LENGTH = 20 << 1;

  private static boolean fastutil_loaded;

  public static void resolveAndLoad(ClassLoader loader, ProxyServer server, Logger logger, String[] repositories, String[] libraries) { // TODO versioning
    try {
      final MethodHandle addURL = Reflection.findVirtualVoid(loader.getClass(), "addPath", Path.class);

      Path librariesDirectory = Path.of("libraries");
      for (String library : libraries) {
        repositoriesLoop:
        for (int repositoryIndex = 0, repositoriesAmount = repositories.length; repositoryIndex < repositoriesAmount; ++repositoryIndex) {
          final String repository = repositories[repositoryIndex];
          final boolean needExtraSlash = repository.charAt(repository.length() - 1) != '/';

          final Path jarPath = librariesDirectory.resolve(library);
          final String jarUrl = needExtraSlash ? (repository + '/' + library) : (repository + library);

          final Path sha1Path = librariesDirectory.resolve(library + ".sha1");
          final String sha1Url = needExtraSlash ? (repository + '/' + library + ".sha1") : (repository + library + ".sha1");

          byte[] expectedHash;
          if (Files.exists(sha1Path) && Files.exists(jarPath) && System.currentTimeMillis() - sha1Path.toFile().lastModified() < LibLoader.TWO_WEEKS_MILLIS) {
            expectedHash = Files.readAllBytes(sha1Path);
          } else {
            Files.createDirectories(jarPath.getParent());
            logger.info("Fetching {}", sha1Url);
            try (InputStream inputStream = new URL(sha1Url).openStream()) {
              expectedHash = inputStream.readNBytes(LibLoader.SHA1_HEX_LENGTH);
              Files.deleteIfExists(sha1Path);
              Files.write(sha1Path, expectedHash);
            } catch (Throwable t) {
              if (t instanceof FileNotFoundException) {
                if (repositoryIndex + 1 == repositoriesAmount) {
                  logger.warn("Couldn't fetch file from {}, no repositories left, shutting down the server", repository);
                  server.shutdown();
                  return;
                } else {
                  logger.warn("Couldn't fetch file from {}, trying next repo", repository);
                  continue;
                }
              } else {
                logger.warn("Unable to fetch {}", sha1Url);
                expectedHash = null;
              }
            }
          }

          if (!Files.exists(jarPath) || LibLoader.notMatches(expectedHash, jarPath)) {
            logger.info("Downloading {}", jarUrl);
            int attempt = 0;
            do {
              if (attempt == LibLoader.MAX_ATTEMPTS) {
                if (repositoryIndex + 1 == repositoriesAmount) {
                  logger.error("Download failed after " + LibLoader.MAX_ATTEMPTS + " times, shutting down the server");
                  server.shutdown();
                  return;
                } else {
                  logger.error("Download failed after " + LibLoader.MAX_ATTEMPTS + " times, trying next repo");
                  continue repositoriesLoop;
                }
              } else if (attempt != 0) {
                logger.warn("Trying again");
              }

              try (InputStream inputStream = new URL(jarUrl).openStream()) {
                Files.copy(inputStream, jarPath, StandardCopyOption.REPLACE_EXISTING);
              } catch (Throwable t) {
                if (t instanceof FileNotFoundException) {
                  if (repositoryIndex + 1 == repositoriesAmount) {
                    logger.warn("Couldn't find file in {}, no repositories left, shutting down the server", repository);
                    server.shutdown();
                    return;
                  } else {
                    logger.warn("Couldn't find file in {}, trying next repo", repository);
                    continue repositoriesLoop;
                  }
                } else {
                  logger.error("Failed to download", t);
                }
              }

              ++attempt;
            } while (LibLoader.notMatches(expectedHash, jarPath));
          }

          if (library.startsWith("it/unimi/dsi/fastutil/")) {
            // We're doing it because velocity already shades some fastutil classes, but does it selfishly
            if (!LibLoader.fastutil_loaded) {
              ClassLoader classLoader = server.getClass().getClassLoader();
              Reflection.findVirtualVoid(classLoader.getClass(), "appendToClassPathForInstrumentation", String.class).invoke(classLoader, jarPath.toString());
              LibLoader.fastutil_loaded = true;
            }
          } else {
            addURL.invoke(loader, jarPath);
          }

          logger.info("Loaded library {}", jarPath.toAbsolutePath());
          break;
        }
      }
    } catch (Throwable t) {
      throw new RuntimeException("An exception has occurred whilst loading libraries", t);
    }
  }

  private static boolean notMatches(byte[] expectedHash, Path jarPath) throws IOException {
    return expectedHash != null && !Arrays.equals(expectedHash, Hex.encodeBytes(Hashing.sha1(Files.readAllBytes(jarPath))));
  }
}
