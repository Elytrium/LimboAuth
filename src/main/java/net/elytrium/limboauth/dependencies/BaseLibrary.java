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

package net.elytrium.limboauth.dependencies;

import edu.umd.cs.findbugs.annotations.SuppressFBWarnings;
import java.io.IOException;
import java.io.InputStream;
import java.net.MalformedURLException;
import java.net.URL;
import java.nio.file.Files;
import java.nio.file.Path;
import java.nio.file.StandardCopyOption;

public enum BaseLibrary {
  H2_V1(
      "com.h2database",
      "h2",
      "1.4.200"
  ),
  H2_V2(
      "com.h2database",
      "h2",
      "2.1.214"
  ),
  MYSQL(
      "com.mysql",
      "mysql-connector-j",
      "8.0.31"
  ),
  POSTGRESQL(
      "org.postgresql",
      "postgresql",
      "42.5.1"
  ),
  SQLITE(
      "org.xerial",
      "sqlite-jdbc",
      "3.40.0.0"
  );

  private final Path filenamePath;
  private final URL mavenRepoURL;

  BaseLibrary(String groupId, String artifactId, String version) {
    String mavenPath = String.format("%s/%s/%s/%s-%s.jar",
        groupId.replace(".", "/"),
        artifactId,
        version,
        artifactId,
        version
    );

    this.filenamePath = Path.of("libraries/" + mavenPath);

    try {
      this.mavenRepoURL = new URL("https://repo1.maven.org/maven2/" + mavenPath);
    } catch (MalformedURLException e) {
      throw new IllegalArgumentException(e);
    }
  }

  @SuppressFBWarnings("NP_NULL_ON_SOME_PATH_FROM_RETURN_VALUE")
  public URL getClassLoaderURL() throws MalformedURLException {
    if (!Files.exists(this.filenamePath)) {
      try {
        try (InputStream in = this.mavenRepoURL.openStream()) {
          Files.createDirectories(this.filenamePath.getParent());
          Files.copy(in, Files.createFile(this.filenamePath), StandardCopyOption.REPLACE_EXISTING);
        }
      } catch (IOException e) {
        throw new IllegalArgumentException(e);
      }
    }

    return this.filenamePath.toUri().toURL();
  }
}
