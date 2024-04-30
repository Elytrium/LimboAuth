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

package net.elytrium.limboauth.utils;

import java.util.List;
import java.util.random.RandomGenerator;
import java.util.random.RandomGeneratorFactory;

public class Random {

  private static final RandomGeneratorFactory<RandomGenerator> FACTORY = RandomGeneratorFactory.of("Xoroshiro128PlusPlus");
  private static final ThreadLocal<RandomGenerator> RANDOM = ThreadLocal.withInitial(Random.FACTORY::create);

  public static byte[] nextBytes(int length) {
    byte[] bytes = new byte[length];
    Random.RANDOM.get().nextBytes(bytes);
    return bytes;
  }

  public static float nextSign(float value) {
    return Random.nextBoolean() ? value : -value;
  }

  public static double nextSign(double value) {
    return Random.nextBoolean() ? value : -value;
  }

  public static int nextSign(int value) {
    return Random.nextBoolean() ? value : -value;
  }

  public static long nextSign(long value) {
    return Random.nextBoolean() ? value : -value;
  }

  public static boolean nextBoolean() {
    return Random.get().nextBoolean();
  }

  public static float nextFloat() {
    return Random.get().nextFloat();
  }

  public static float nextFloat(float bound) {
    return Random.get().nextFloat(bound);
  }

  public static float nextFloat(float origin, float bound) {
    return Random.get().nextFloat(origin, bound);
  }

  public static double nextDouble() {
    return Random.get().nextDouble();
  }

  public static double nextDouble(double bound) {
    return Random.get().nextDouble(bound);
  }

  public static double nextDouble(double origin, double bound) {
    return Random.get().nextDouble(origin, bound);
  }

  public static int nextInt() {
    return Random.get().nextInt();
  }

  public static int nextInt(int bound) {
    return Random.get().nextInt(bound);
  }

  public static int nextInt(int origin, int bound) {
    return Random.get().nextInt(origin, bound);
  }

  public static long nextLong() {
    return Random.get().nextLong();
  }

  public static long nextLong(long bound) {
    return Random.get().nextLong(bound);
  }

  public static long nextLong(long origin, long bound) {
    return Random.get().nextLong(origin, bound);
  }

  public static <K, L extends List<K>> L shuffle(final L l) {
    RandomGenerator random = Random.get();
    int i = l.size();
    while (i-- != 0) {
      final int p = random.nextInt(i + 1);
      final K t = l.get(i);
      l.set(i, l.get(p));
      l.set(p, t);
    }

    return l;
  }

  public static <K> K[] shuffle(final K[] a) {
    RandomGenerator random = Random.get();
    int i = a.length;
    while (i-- != 0) {
      final int p = random.nextInt(i + 1);
      final K t = a[i];
      a[i] = a[p];
      a[p] = t;
    }

    return a;
  }

  public static <K> K next(final List<K> l) {
    return l.get(Random.get().nextInt(l.size()));
  }

  private static RandomGenerator get() {
    return Random.RANDOM.get();
  }

  public static RandomGenerator get(long seed) {
    return Random.FACTORY.create(seed);
  }
}
