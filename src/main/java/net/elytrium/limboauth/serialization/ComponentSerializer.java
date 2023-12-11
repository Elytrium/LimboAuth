package net.elytrium.limboauth.serialization;

import net.elytrium.serializer.placeholders.Placeholders;
import net.kyori.adventure.text.Component;
import net.kyori.adventure.text.minimessage.MiniMessage;
import net.kyori.adventure.text.serializer.gson.GsonComponentSerializer;
import net.kyori.adventure.text.serializer.legacy.LegacyComponentSerializer;
import net.kyori.adventure.text.serializer.plain.PlainTextComponentSerializer;

@SuppressWarnings("NullableProblems")
public enum ComponentSerializer implements net.kyori.adventure.text.serializer.ComponentSerializer<Component, Component, String> {

  LEGACY_AMPERSAND(LegacyComponentSerializer.builder().character(LegacyComponentSerializer.AMPERSAND_CHAR).extractUrls().hexColors().build()),
  LEGACY_SECTION(LegacyComponentSerializer.builder().extractUrls().hexColors().build()),
  MINIMESSAGE(MiniMessage.miniMessage()),
  GSON(GsonComponentSerializer.gson()),
  PLAIN(PlainTextComponentSerializer.plainText());

  private final net.kyori.adventure.text.serializer.ComponentSerializer<Component, Component, String> delegate;

  @SuppressWarnings("unchecked")
  ComponentSerializer(net.kyori.adventure.text.serializer.ComponentSerializer<Component, ? extends Component, String> delegate) {
    this.delegate = (net.kyori.adventure.text.serializer.ComponentSerializer<Component, Component, String>) delegate;
  }

  @Override
  public Component deserialize(String input) {
    return this.delegate.deserialize(input);
  }

  @Override
  public Component deserializeOrNull(String input) {
    return this.delegate.deserializeOrNull(input);
  }

  @Override
  public Component deserializeOr(String input, Component fallback) {
    return this.delegate.deserializeOr(input, fallback);
  }

  @Override
  public String serialize(Component component) {
    return this.delegate.serialize(component);
  }

  @Override
  public String serializeOrNull(Component component) {
    return this.delegate.serializeOrNull(component);
  }

  @Override
  public String serializeOr(Component component, String fallback) {
    return this.delegate.serializeOr(component, fallback);
  }

  public static Component replace(Component value, Object... values) {
    return Placeholders.replace(value, values);
  }

  public static <H> Component replaceFor(H holder, Component value, Object... values) {
    return Placeholders.replaceFor(holder, value, values);
  }
}
