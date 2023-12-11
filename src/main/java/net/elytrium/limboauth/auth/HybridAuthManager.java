package net.elytrium.limboauth.auth;

import com.google.common.net.UrlEscapers;
import com.google.gson.JsonElement;
import com.google.gson.JsonObject;
import com.google.gson.JsonParser;
import com.velocitypowered.proxy.VelocityServer;
import java.util.ArrayDeque;
import java.util.List;
import java.util.Locale;
import java.util.Queue;
import java.util.UUID;
import java.util.concurrent.CompletableFuture;
import java.util.concurrent.ExecutionException;
import java.util.function.Function;
import net.elytrium.limboauth.LimboAuth;
import net.elytrium.limboauth.Settings;
import net.elytrium.limboauth.cache.CachedPremiumUser;
import net.elytrium.limboauth.data.PlayerData;
import org.asynchttpclient.AsyncHttpClient;
import org.asynchttpclient.ListenableFuture;
import org.asynchttpclient.Response;
import org.jooq.DSLContext;

public class HybridAuthManager {

  private final LimboAuth plugin;
  private final AsyncHttpClient httpClient;

  public HybridAuthManager(LimboAuth plugin) {
    this.plugin = plugin;
    this.httpClient = ((VelocityServer) plugin.getServer()).getAsyncHttpClient();
  }

  private boolean validateScheme(JsonElement jsonElement, List<String> scheme) {
    if (!scheme.isEmpty()) {
      if (!(jsonElement instanceof JsonObject object)) {
        return false;
      }

      for (String field : scheme) {
        if (!object.has(field)) {
          return false;
        }
      }
    }

    return true;
  }

  public CompletableFuture<PremiumResponse> isPremiumExternal(String nickname) {
    CompletableFuture<PremiumResponse> completableFuture = new CompletableFuture<>();
    ListenableFuture<Response> responseListenable = this.httpClient.prepareGet(String.format(Settings.HEAD.isPremiumAuthUrl, UrlEscapers.urlFormParameterEscaper().escape(nickname))).execute();

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
        this.plugin.getLogger().error("Unable to authenticate with Mojang.", e);
        completableFuture.complete(PremiumResponse.ERROR);
      }
    }, this.plugin.getExecutor());

    return completableFuture;
  }

  public CompletableFuture<PremiumResponse> isPremiumInternal(String nickname) {
    CompletableFuture<PremiumResponse> completableFuture = new CompletableFuture<>();
    DSLContext context = this.plugin.getDatabase().getContext();
    context.selectCount().from(PlayerData.Table.INSTANCE).where(
        PlayerData.Table.LOWERCASE_NICKNAME_FIELD.eq(nickname).and(PlayerData.Table.HASH_FIELD.ne(""))
    ).limit(1).fetchAsync().handle((result, t) -> {
      if (result == null) {
        this.plugin.getLogger().error("Unable to check if account is premium.", t);
        completableFuture.complete(PremiumResponse.ERROR);
      } else {
        completableFuture.complete(result.get(0).value1() == 0 ? PremiumResponse.UNKNOWN : PremiumResponse.CRACKED);
      }

      return null;
    });
    context.selectCount().from(PlayerData.Table.INSTANCE).where(
        PlayerData.Table.LOWERCASE_NICKNAME_FIELD.eq(nickname).and(PlayerData.Table.HASH_FIELD.eq(""))
    ).fetchAsync().handle((result, t) -> {
      if (result == null) {
        this.plugin.getLogger().error("Unable to check if account is premium.", t);
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
