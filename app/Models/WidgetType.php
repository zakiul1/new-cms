<?php

namespace App\Models;

/**
 * Base class for all CMS widgets.
 *
 * A "widget type" is a PHP class (ex: TextWidget::class, MenuWidget::class)
 * registered into WidgetRegistry. SidebarRenderer will instantiate and call render().
 *
 * You can extend this class for every widget type.
 */
abstract class WidgetType
{
    /**
     * Unique type key, stored in DB (ex: "text", "menu").
     */
    abstract public static function type(): string;

    /**
     * Human readable name (ex: "Text", "Menu").
     */
    abstract public static function label(): string;

    /**
     * Optional short description for admin UI.
     */
    public static function description(): ?string
    {
        return null;
    }

    /**
     * Optional icon name (heroicon string, etc.).
     */
    public static function icon(): ?string
    {
        return null;
    }

    /**
     * Default widget settings (merged into saved config).
     */
    public static function defaultSettings(): array
    {
        return [];
    }

    /**
     * Optional: settings schema definition for your admin UI.
     *
     * Keep this framework-agnostic (plain arrays) so you can render using
     * Filament / custom UI later.
     *
     * Example return:
     * [
     *   ['key' => 'title', 'type' => 'text', 'label' => 'Title'],
     *   ['key' => 'content', 'type' => 'textarea', 'label' => 'Content'],
     * ]
     */
    public static function settingsSchema(): array
    {
        return [];
    }

    /**
     * Normalize / validate settings before saving or rendering.
     */
    public static function normalizeSettings(array $settings): array
    {
        // Merge defaults, then allow widget to post-process
        $settings = array_replace_recursive(static::defaultSettings(), $settings);

        // Basic safety: never return non-array values
        return is_array($settings) ? $settings : static::defaultSettings();
    }

    /**
     * Render widget HTML.
     *
     * @param  array  $settings  Widget settings from DB (already normalized).
     * @param  array  $ctx       Optional context (theme, request, user, etc).
     */
    abstract public function render(array $settings, array $ctx = []): string;
}
