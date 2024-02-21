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

package net.elytrium.limboauth.auth;

import com.google.gson.JsonElement;
import com.google.gson.JsonObject;
import com.velocitypowered.proxy.VelocityServer;
import java.net.http.HttpClient;
import java.util.ArrayDeque;
import java.util.List;
import java.util.Locale;
import java.util.Queue;
import java.util.UUID;
import java.util.concurrent.CompletableFuture;
import java.util.function.Function;
import net.elytrium.limboauth.LimboAuth;
import net.elytrium.limboauth.Settings;
import net.elytrium.limboauth.cache.CachedPremiumUser;
import net.elytrium.limboauth.data.Database;
import net.elytrium.limboauth.data.PlayerData;

public class HybridAuthManager {

  private final LimboAuth plugin;
  private final VelocityServer server;

  public HybridAuthManager(LimboAuth plugin) {
    this.plugin = plugin;
    this.server = (VelocityServer) plugin.getServer();
  }

  private boolean validateScheme(JsonElement jsonElement, List<String> scheme) {
    if (!scheme.isEmpty()) {
      if (jsonElement instanceof JsonObject object) {
        for (String field : scheme) {
          if (!object.has(field)) {
            return false;
          }
        }
      } else {
        return false;
      }
    }

    return true;
  }

  public CompletableFuture<PremiumResponse> isPremiumExternal(String nickname) {
    CompletableFuture<PremiumResponse> completableFuture = new CompletableFuture<>();
    HttpClient httpClient = this.server.createHttpClient();
    /*
    ListenableFuture<Response> responseListenable = httpClient.prepareGet(String.format(Settings.HEAD.isPremiumAuthUrl, UrlEscapers.urlFormParameterEscaper().escape(nickname))).execute();
    responseListenable.addListener(() -> {
      try {
        Response response = responseListenable.get();
        int statusCode = response.getStatusCode();

        if (Settings.HEAD.statusCodeRateLimit.contains(statusCode)) {
          completableFuture.complete(PremiumResponse.RATE_LIMIT);
          return;
        }

        JsonElement jsonElement = JsonParser.parseString(response.getResponseBody());

        if (Settings.HEAD.statusCodeUserExists.contains(statusCode) && this.validateScheme(jsonElement, Settings.HEAD.userExistsJsonValidatorFields)) {
          completableFuture.complete(new PremiumResponse(PremiumState.PREMIUM_USERNAME, ((JsonObject) jsonElement).get(Settings.HEAD.jsonUuidField).getAsString()));
          return;
        }

        if (Settings.HEAD.statusCodeUserNotExists.contains(statusCode) && this.validateScheme(jsonElement, Settings.HEAD.userNotExistsJsonValidatorFields)) {
          completableFuture.complete(PremiumResponse.CRACKED);
          return;
        }

        completableFuture.complete(PremiumResponse.ERROR);
      } catch (ExecutionException | InterruptedException e) {
        this.plugin.getLogger().error("Unable to authenticate with Mojang", e);
        completableFuture.complete(PremiumResponse.ERROR);
      }
    }, this.plugin.getExecutor());
    */
    return completableFuture;
  }

  public CompletableFuture<PremiumResponse> isPremiumInternal(String nickname) {
    CompletableFuture<PremiumResponse> completableFuture = new CompletableFuture<>();
    Database database = this.plugin.getDatabase();
    database.selectCount().from(PlayerData.Table.INSTANCE).where(
        PlayerData.Table.LOWERCASE_NICKNAME_FIELD.eq(nickname).and(PlayerData.Table.HASH_FIELD.ne(""))
    ).limit(1).fetchAsync().handle((result, t) -> {
      if (result == null) {
        this.plugin.getLogger().error("Unable to check if account is premium", t);
        completableFuture.complete(PremiumResponse.ERROR);
      } else {
        completableFuture.complete(result.get(0).value1() == 0 ? PremiumResponse.UNKNOWN : PremiumResponse.CRACKED);
      }

      return null;
    });
    database.selectCount().from(PlayerData.Table.INSTANCE).where(PlayerData.Table.LOWERCASE_NICKNAME_FIELD.eq(nickname).and(PlayerData.Table.HASH_FIELD.eq(""))).fetchAsync().handle((result, t) -> {
      if (result == null) {
        this.plugin.getLogger().error("Unable to check if account is premium", t);
        completableFuture.complete(PremiumResponse.ERROR);
      } else {
        completableFuture.complete(result.get(0).value1() == 0 ? PremiumResponse.UNKNOWN : PremiumResponse.PREMIUM);
      }

      return null;
    });
    return completableFuture;
  }

  public CompletableFuture<Boolean> isPremiumUuid(UUID uuid) {
    CompletableFuture<Boolean> completableFuture = new CompletableFuture<>();
    this.plugin.getDatabase().selectCount().from(PlayerData.Table.INSTANCE)
        .where(PlayerData.Table.PREMIUM_UUID_FIELD.eq(uuid).and(PlayerData.Table.HASH_FIELD.eq("")))
        .fetchAsync()
        .thenAccept(result -> completableFuture.complete(result.get(0).value1() != 0));
    return completableFuture;
  }

  private CompletableFuture<Boolean> checkIsPremiumAndCacheFinal(String lowercaseNickname, boolean premium, boolean unknown, boolean wasRateLimited, boolean wasError, UUID uuid) {
    if (unknown) {
      if (uuid != null) {
        CompletableFuture<Boolean> isPremiumFuture = new CompletableFuture<>();

        this.isPremiumUuid(uuid).thenAccept(isPremium -> {
          if (isPremium) {
            this.plugin.getCacheManager().cachePremiumUser(lowercaseNickname, true);
            isPremiumFuture.complete(true);
          }
        });

        return isPremiumFuture;
      }

      if (Settings.HEAD.onlineModeNeedAuth) {
        return CompletableFuture.completedFuture(false);
      }
    }

    if (wasRateLimited && unknown || wasRateLimited && wasError) {
      return CompletableFuture.completedFuture(Settings.HEAD.onRateLimitPremium);
    }

    if (wasError && unknown || !premium) {
      return CompletableFuture.completedFuture(Settings.HEAD.onServerErrorPremium);
    }

    this.plugin.getCacheManager().cachePremiumUser(lowercaseNickname, true);
    return CompletableFuture.completedFuture(true);
  }

  private CompletableFuture<Boolean> checkIsPremiumAndCacheStep(Queue<Function<String, CompletableFuture<PremiumResponse>>> queue,
      String lowercaseNickname, boolean premiumFinal, boolean unknownFinal,
      boolean wasRateLimitedFinal, boolean wasErrorFinal, UUID uuidFinal) {
    // TODO loop, no deque
    if (queue.isEmpty()) {
      return this.checkIsPremiumAndCacheFinal(lowercaseNickname, premiumFinal, unknownFinal, wasRateLimitedFinal, wasErrorFinal, uuidFinal);
    }

    return queue.poll().apply(lowercaseNickname).thenCompose(check -> {
      boolean premium = premiumFinal;
      boolean unknown = unknownFinal;
      boolean wasRateLimited = wasRateLimitedFinal;
      boolean wasError = wasErrorFinal;
      UUID uuid = check.getUuid() == null ? uuidFinal : check.getUuid();

      switch (check.getState()) {
        case CRACKED -> {
          this.plugin.getCacheManager().cachePremiumUser(lowercaseNickname, false);
          return CompletableFuture.completedFuture(false);
        }
        case PREMIUM -> {
          this.plugin.getCacheManager().cachePremiumUser(lowercaseNickname, true);
          return CompletableFuture.completedFuture(true);
        }
        case PREMIUM_USERNAME -> premium = true;
        case UNKNOWN -> unknown = true;
        case RATE_LIMIT -> wasRateLimited = true;
        default -> wasError = true;
      }

      return this.checkIsPremiumAndCacheStep(queue, lowercaseNickname, premium, unknown, wasRateLimited, wasError, uuid);
    });
  }

  private CompletableFuture<Boolean> checkIsPremiumAndCache(String nickname, Queue<Function<String, CompletableFuture<PremiumResponse>>> queue) {
    String lowercaseNickname = nickname.toLowerCase(Locale.ROOT);
    CachedPremiumUser cachedPremiumUser = this.plugin.getCacheManager().getPremiumUser(lowercaseNickname);
    return cachedPremiumUser == null
        ? this.checkIsPremiumAndCacheStep(queue, lowercaseNickname, false, false, false, false, null)
        : CompletableFuture.completedFuture(cachedPremiumUser.isPremium());

  }

  public CompletableFuture<Boolean> isPremium(String nickname) {
    return Settings.HEAD.forceOfflineMode ? CompletableFuture.completedFuture(false)
        : Settings.HEAD.checkPremiumPriorityInternal ? this.checkIsPremiumAndCache(nickname, new ArrayDeque<>(List.of(this::isPremiumInternal, this::isPremiumExternal)))
            : this.checkIsPremiumAndCache(nickname, new ArrayDeque<>(List.of(this::isPremiumExternal, this::isPremiumInternal)));
  }
}
