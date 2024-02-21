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

import java.lang.invoke.MethodHandle;
import java.lang.invoke.MethodHandles;
import java.lang.invoke.MethodType;
import java.lang.reflect.Field;
import net.elytrium.commons.utils.reflection.ReflectionException;
import sun.misc.Unsafe;

public class Reflection {

  private static final MethodType VOID = MethodType.methodType(void.class);

  public static final Unsafe UNSAFE;
  public static final MethodHandles.Lookup IMPL_LOOKUP;

  public static MethodHandle findStatic(Class<?> clazz, String name, Class<?> returnType) {
    try {
      return Reflection.IMPL_LOOKUP.findStatic(clazz, name, MethodType.methodType(returnType));
    } catch (NoSuchMethodException | IllegalAccessException e) {
      throw new ReflectionException(e);
    }
  }

  public static MethodHandle findStatic(Class<?> clazz, String name, Class<?> returnType, Class<?>... parameters) {
    try {
      return Reflection.IMPL_LOOKUP.findStatic(clazz, name, MethodType.methodType(returnType, parameters));
    } catch (NoSuchMethodException | IllegalAccessException e) {
      throw new ReflectionException(e);
    }
  }

  public static MethodHandle findStaticVoid(Class<?> clazz, String name) {
    try {
      return Reflection.IMPL_LOOKUP.findStatic(clazz, name, Reflection.VOID);
    } catch (NoSuchMethodException | IllegalAccessException e) {
      throw new ReflectionException(e);
    }
  }

  public static MethodHandle findStaticVoid(Class<?> clazz, String name, Class<?>... parameters) {
    try {
      return Reflection.IMPL_LOOKUP.findStatic(clazz, name, MethodType.methodType(void.class, parameters));
    } catch (NoSuchMethodException | IllegalAccessException e) {
      throw new ReflectionException(e);
    }
  }

  public static MethodHandle findVirtual(Class<?> clazz, String name, Class<?> returnType) {
    try {
      return Reflection.IMPL_LOOKUP.findVirtual(clazz, name, MethodType.methodType(returnType));
    } catch (NoSuchMethodException | IllegalAccessException e) {
      throw new ReflectionException(e);
    }
  }

  public static MethodHandle findVirtual(Class<?> clazz, String name, Class<?> returnType, Class<?>... parameters) {
    try {
      return Reflection.IMPL_LOOKUP.findVirtual(clazz, name, MethodType.methodType(returnType, parameters));
    } catch (NoSuchMethodException | IllegalAccessException e) {
      throw new ReflectionException(e);
    }
  }

  public static MethodHandle findVirtualVoid(Class<?> clazz, String name) {
    try {
      return Reflection.IMPL_LOOKUP.findVirtual(clazz, name, Reflection.VOID);
    } catch (NoSuchMethodException | IllegalAccessException e) {
      throw new ReflectionException(e);
    }
  }

  public static MethodHandle findVirtualVoid(Class<?> clazz, String name, Class<?>... parameters) {
    try {
      return Reflection.IMPL_LOOKUP.findVirtual(clazz, name, MethodType.methodType(void.class, parameters));
    } catch (NoSuchMethodException | IllegalAccessException e) {
      throw new ReflectionException(e);
    }
  }

  public static MethodHandle findConstructor(Class<?> clazz) {
    try {
      return Reflection.IMPL_LOOKUP.findConstructor(clazz, Reflection.VOID);
    } catch (NoSuchMethodException | IllegalAccessException e) {
      throw new ReflectionException(e);
    }
  }

  public static MethodHandle findConstructor(Class<?> clazz, Class<?>... parameters) {
    try {
      return Reflection.IMPL_LOOKUP.findConstructor(clazz, MethodType.methodType(void.class, parameters));
    } catch (NoSuchMethodException | IllegalAccessException e) {
      throw new ReflectionException(e);
    }
  }

  public static MethodHandle findGetter(Class<?> clazz, String name, Class<?> type) {
    try {
      return Reflection.IMPL_LOOKUP.findGetter(clazz, name, type);
    } catch (NoSuchFieldException | IllegalAccessException e) {
      throw new ReflectionException(e);
    }
  }

  public static MethodHandle findStaticGetter(Class<?> clazz, String name, Class<?> type) {
    try {
      return Reflection.IMPL_LOOKUP.findStaticGetter(clazz, name, type);
    } catch (NoSuchFieldException | IllegalAccessException e) {
      throw new ReflectionException(e);
    }
  }

  public static MethodHandle findSetter(Class<?> clazz, String name, Class<?> type) {
    try {
      return Reflection.IMPL_LOOKUP.findSetter(clazz, name, type);
    } catch (NoSuchFieldException | IllegalAccessException e) {
      throw new ReflectionException(e);
    }
  }

  public static MethodHandle findStaticSetter(Class<?> clazz, String name, Class<?> type) {
    try {
      return Reflection.IMPL_LOOKUP.findStaticSetter(clazz, name, type);
    } catch (NoSuchFieldException | IllegalAccessException e) {
      throw new ReflectionException(e);
    }
  }

  static {
    try {
      Field theUnsafe = Unsafe.class.getDeclaredField("theUnsafe");
      theUnsafe.setAccessible(true);
      UNSAFE = (Unsafe) theUnsafe.get(null);

      Field implLookupField = MethodHandles.Lookup.class.getDeclaredField("IMPL_LOOKUP");
      IMPL_LOOKUP = (MethodHandles.Lookup) UNSAFE.getObject(UNSAFE.staticFieldBase(implLookupField), UNSAFE.staticFieldOffset(implLookupField));
    } catch (NoSuchFieldException | IllegalAccessException e) {
      throw new RuntimeException(e);
    }
  }
}
