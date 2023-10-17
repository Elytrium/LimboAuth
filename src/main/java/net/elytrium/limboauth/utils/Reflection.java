package net.elytrium.limboauth.utils;

import java.lang.invoke.MethodHandle;
import java.lang.invoke.MethodHandles;
import java.lang.invoke.MethodType;
import java.lang.reflect.Field;
import sun.misc.Unsafe;

@SuppressWarnings("deprecation")
public class Reflection {

  private static final MethodHandles.Lookup IMPL_LOOKUP;

  public static MethodHandle void1(Class<?> clazz, String name, Class<?> parameter1) {
    try {
      return Reflection.IMPL_LOOKUP.findVirtual(clazz, name, MethodType.methodType(void.class, parameter1));
    } catch (NoSuchMethodException | IllegalAccessException e) {
      throw new RuntimeException(e);
    }
  }

  public static MethodHandle getter1(Class<?> clazz, String name, Class<?> parameter1) {
    try {
      return Reflection.IMPL_LOOKUP.findGetter(clazz, name, parameter1);
    } catch (NoSuchFieldException | IllegalAccessException e) {
      throw new RuntimeException(e);
    }
  }

  static {
    try {
      Field unsafeField = Unsafe.class.getDeclaredField("theUnsafe");
      unsafeField.setAccessible(true);
      Unsafe unsafe = (Unsafe) unsafeField.get(null);

      Field implLookupField = MethodHandles.Lookup.class.getDeclaredField("IMPL_LOOKUP");
      IMPL_LOOKUP = (MethodHandles.Lookup) unsafe.getObject(unsafe.staticFieldBase(implLookupField), unsafe.staticFieldOffset(implLookupField));
    } catch (NoSuchFieldException | IllegalAccessException e) {
      throw new RuntimeException(e);
    }
  }
}