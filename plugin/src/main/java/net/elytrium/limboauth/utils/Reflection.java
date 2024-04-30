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
import java.lang.invoke.VarHandle;
import java.lang.reflect.Field;
import net.elytrium.commons.utils.reflection.ReflectionException;
import sun.misc.Unsafe;

public class Reflection {

  private static final MethodType VOID = MethodType.methodType(void.class);

  public static final Unsafe UNSAFE;
  public static final MethodHandles.Lookup LOOKUP;

  public static MethodHandle findVirtual(Class<?> clazz, String name, Class<?> returnType) {
    try {
      return Reflection.LOOKUP.findVirtual(clazz, name, MethodType.methodType(returnType));
    } catch (NoSuchMethodException | IllegalAccessException e) {
      throw new ReflectionException(e);
    }
  }

  public static MethodHandle findVirtual(Class<?> clazz, String name, Class<?> returnType, Class<?>... parameters) {
    try {
      return Reflection.LOOKUP.findVirtual(clazz, name, MethodType.methodType(returnType, parameters));
    } catch (NoSuchMethodException | IllegalAccessException e) {
      throw new ReflectionException(e);
    }
  }

  public static MethodHandle findVirtualVoid(Class<?> clazz, String name) {
    try {
      return Reflection.LOOKUP.findVirtual(clazz, name, Reflection.VOID);
    } catch (NoSuchMethodException | IllegalAccessException e) {
      throw new ReflectionException(e);
    }
  }

  public static MethodHandle findVirtualVoid(Class<?> clazz, String name, Class<?>... parameters) {
    try {
      return Reflection.LOOKUP.findVirtual(clazz, name, MethodType.methodType(void.class, parameters));
    } catch (NoSuchMethodException | IllegalAccessException e) {
      throw new ReflectionException(e);
    }
  }

  public static MethodHandle findStatic(Class<?> clazz, String name, Class<?> returnType) {
    try {
      return Reflection.LOOKUP.findStatic(clazz, name, MethodType.methodType(returnType));
    } catch (NoSuchMethodException | IllegalAccessException e) {
      throw new ReflectionException(e);
    }
  }

  public static MethodHandle findStatic(Class<?> clazz, String name, Class<?> returnType, Class<?>... parameters) {
    try {
      return Reflection.LOOKUP.findStatic(clazz, name, MethodType.methodType(returnType, parameters));
    } catch (NoSuchMethodException | IllegalAccessException e) {
      throw new ReflectionException(e);
    }
  }

  public static MethodHandle findStaticVoid(Class<?> clazz, String name) {
    try {
      return Reflection.LOOKUP.findStatic(clazz, name, Reflection.VOID);
    } catch (NoSuchMethodException | IllegalAccessException e) {
      throw new ReflectionException(e);
    }
  }

  public static MethodHandle findStaticVoid(Class<?> clazz, String name, Class<?>... parameters) {
    try {
      return Reflection.LOOKUP.findStatic(clazz, name, MethodType.methodType(void.class, parameters));
    } catch (NoSuchMethodException | IllegalAccessException e) {
      throw new ReflectionException(e);
    }
  }

  public static MethodHandle findConstructor(Class<?> clazz) {
    try {
      return Reflection.LOOKUP.findConstructor(clazz, Reflection.VOID);
    } catch (NoSuchMethodException | IllegalAccessException e) {
      throw new ReflectionException(e);
    }
  }

  public static MethodHandle findConstructor(Class<?> clazz, Class<?>... parameters) {
    try {
      return Reflection.LOOKUP.findConstructor(clazz, MethodType.methodType(void.class, parameters));
    } catch (NoSuchMethodException | IllegalAccessException e) {
      throw new ReflectionException(e);
    }
  }

  public static MethodHandle findGetter(Class<?> clazz, String name, Class<?> type) {
    try {
      return Reflection.LOOKUP.findGetter(clazz, name, type);
    } catch (NoSuchFieldException | IllegalAccessException e) {
      throw new ReflectionException(e);
    }
  }

  public static MethodHandle findStaticGetter(Class<?> clazz, String name, Class<?> type) {
    try {
      return Reflection.LOOKUP.findStaticGetter(clazz, name, type);
    } catch (NoSuchFieldException | IllegalAccessException e) {
      throw new ReflectionException(e);
    }
  }

  public static MethodHandle findSetter(Class<?> clazz, String name, Class<?> type) {
    try {
      return Reflection.LOOKUP.findSetter(clazz, name, type);
    } catch (NoSuchFieldException | IllegalAccessException e) {
      throw new ReflectionException(e);
    }
  }

  public static MethodHandle findStaticSetter(Class<?> clazz, String name, Class<?> type) {
    try {
      return Reflection.LOOKUP.findStaticSetter(clazz, name, type);
    } catch (NoSuchFieldException | IllegalAccessException e) {
      throw new ReflectionException(e);
    }
  }

  public static VarHandle findVarHandle(Class<?> clazz, String name, Class<?> type) {
    try {
      return Reflection.LOOKUP.findVarHandle(clazz, name, type);
    } catch (NoSuchFieldException | IllegalAccessException e) {
      throw new ReflectionException(e);
    }
  }

  public static VarHandle findStaticVarHandle(Class<?> clazz, String name, Class<?> type) {
    try {
      return Reflection.LOOKUP.findStaticVarHandle(clazz, name, type);
    } catch (NoSuchFieldException | IllegalAccessException e) {
      throw new ReflectionException(e);
    }
  }

  public static Class<?> findClass(String className) {
    try {
      return Class.forName(className);
    } catch (ClassNotFoundException e) {
      throw new RuntimeException(e);
    }
  }

  static {
    try {
      Field theUnsafe = Unsafe.class.getDeclaredField("theUnsafe");
      theUnsafe.setAccessible(true);
      UNSAFE = (Unsafe) theUnsafe.get(null);

      Field implLookupField = MethodHandles.Lookup.class.getDeclaredField("IMPL_LOOKUP");
      LOOKUP = (MethodHandles.Lookup) UNSAFE.getObject(UNSAFE.staticFieldBase(implLookupField), UNSAFE.staticFieldOffset(implLookupField));
    } catch (NoSuchFieldException | IllegalAccessException e) {
      throw new RuntimeException(e);
    }
  }
}
