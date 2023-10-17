package net.elytrium.limboauth.cache;

import com.github.benmanes.caffeine.cache.Caffeine;
import com.github.benmanes.caffeine.cache.Cache;
import com.velocitypowered.api.proxy.Player;
import it.unimi.dsi.fastutil.objects.Object2ObjectMap;
import it.unimi.dsi.fastutil.objects.Object2ObjectMaps;
import it.unimi.dsi.fastutil.objects.Object2ObjectOpenHashMap;
import it.unimi.dsi.fastutil.objects.ObjectOpenHashSet;
import it.unimi.dsi.fastutil.objects.ObjectSet;
import it.unimi.dsi.fastutil.objects.ObjectSets;
import java.net.InetAddress;
import java.util.Locale;
import java.util.UUID;
import java.util.concurrent.TimeUnit;
import net.elytrium.limboauth.Settings;

public class CacheManager {

  private final Cache<String, CachedSessionUser> sessionCache = Caffeine.newBuilder().expireAfterWrite(Settings.HEAD.purgeCacheMillis, TimeUnit.MILLISECONDS).build();
  private final Cache<String, CachedPremiumUser> premiumCache = Caffeine.newBuilder().expireAfterWrite(Settings.HEAD.purgeCacheMillis, TimeUnit.MILLISECONDS).build();
  private final Cache<InetAddress, CachedBruteforceUser> bruteforceCache = Caffeine.newBuilder().expireAfterWrite(Settings.HEAD.purgeCacheMillis, TimeUnit.MILLISECONDS).build();
  private final Object2ObjectMap<UUID, Runnable> postLoginTasks = Object2ObjectMaps.synchronize(new Object2ObjectOpenHashMap<>());
  private final ObjectSet<String> forcedPreviously = ObjectSets.synchronize(new ObjectOpenHashSet<>());

  public void cacheSessionUser(Player player) {
    String username = player.getUsername();
    String lowercaseUsername = username.toLowerCase(Locale.ROOT);
    this.sessionCache.put(lowercaseUsername, new CachedSessionUser(System.currentTimeMillis(), player.getRemoteAddress().getAddress(), username));
  }

  public void cachePremiumUser(String lowercaseUsername, boolean premium) {
    this.premiumCache.put(lowercaseUsername, new CachedPremiumUser(System.currentTimeMillis(), premium));
  }

  public CachedSessionUser getSessionUser(Player player) {
    String username = player.getUsername();
    String lowercaseUsername = username.toLowerCase(Locale.ROOT);
    return this.sessionCache.getIfPresent(lowercaseUsername);
  }

  public CachedPremiumUser getPremiumUser(String lowercaseUsername) {
    return this.premiumCache.getIfPresent(lowercaseUsername);
  }

  public void removePlayerFromCache(String username) {
    this.sessionCache.invalidate(username.toLowerCase(Locale.ROOT));
    this.premiumCache.invalidate(username.toLowerCase(Locale.ROOT));
  }

  public void incrementBruteforceAttempts(Player player) {
    this.incrementBruteforceAttempts(player.getRemoteAddress().getAddress());
  }

  public void incrementBruteforceAttempts(InetAddress address) {
    this.getBruteforceUser(address).incrementAttempts();
  }

  public int getBruteforceAttempts(Player player) {
    return this.getBruteforceAttempts(player.getRemoteAddress().getAddress());
  }

  public int getBruteforceAttempts(InetAddress address) {
    return this.getBruteforceUser(address).getAttempts();
  }

  public CachedBruteforceUser getBruteforceUser(Player player) {
    return this.getBruteforceUser(player.getRemoteAddress().getAddress());
  }

  private CachedBruteforceUser getBruteforceUser(InetAddress address) {
    CachedBruteforceUser user = this.bruteforceCache.getIfPresent(address);
    if (user == null) {
      user = new CachedBruteforceUser(System.currentTimeMillis());
      this.bruteforceCache.put(address, user);
    }

    return user;
  }

  public void clearBruteforceAttempts(InetAddress address) {
    this.bruteforceCache.invalidate(address);
  }

  public void saveForceOfflineMode(String nickname) {
    this.forcedPreviously.add(nickname);
  }

  public void unsetForcedPreviously(String nickname) {
    this.forcedPreviously.remove(nickname);
  }

  public boolean isForcedPreviously(String nickname) {
    return this.forcedPreviously.contains(nickname);
  }

  public void pushPostLoginTask(UUID uuid, Runnable task) {
    this.postLoginTasks.put(uuid, task);
  }

  public Runnable popPostLoginTask(UUID uuid) {
    return this.postLoginTasks.remove(uuid);
  }
}
