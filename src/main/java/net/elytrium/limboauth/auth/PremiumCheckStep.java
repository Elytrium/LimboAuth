package net.elytrium.limboauth.auth;

import java.util.UUID;
import java.util.concurrent.CompletableFuture;
import java.util.function.Function;

@FunctionalInterface
public interface PremiumCheckStep {

  void checkIsPremiumAndCacheStep(Function<String, CompletableFuture<PremiumResponse>> function, String nickname, String lowercaseNickname,
      boolean premium, boolean unknown, boolean wasRateLimited, boolean wasError, UUID uuid);
}
