<?php

use App\Cms\Content\Shortcodes\ShortcodeParser;
use App\Cms\Content\Shortcodes\ShortcodeRegistry;
use App\Cms\Hooks\HookPoints;
use App\Cms\Hooks\Hooks;

if (!function_exists('add_shortcode')) {
    /**
     * Register a shortcode handler.
     * Handler signature: function(array $attrs = [], ?string $content = null, array $ctx = []): string
     */
    function add_shortcode(string $tag, callable $handler): void
    {
        $tag = strtolower(trim($tag));
        if ($tag === '' || !function_exists('app')) {
            return;
        }

        /** @var ShortcodeRegistry $registry */
        $registry = app(ShortcodeRegistry::class);

        $registry->register($tag, function (array $attrs, ?string $content, array $ctx = []) use ($handler): string {
            return (string) $handler($attrs, $content, $ctx);
        });
    }
}

if (!function_exists('shortcode_exists')) {
    function shortcode_exists(string $tag): bool
    {
        if (!function_exists('app')) {
            return false;
        }

        return app(ShortcodeRegistry::class)->has($tag);
    }
}

if (!function_exists('do_shortcode')) {
    /**
     * Runs the CMS content pipeline (includes shortcodes via CMS_THE_CONTENT filter).
     */
    function do_shortcode(string $html, array $ctx = []): string
    {
        if ($html === '' || !function_exists('app')) {
            return $html;
        }

        try {
            /** @var Hooks $hooks */
            $hooks = app(Hooks::class);

            return (string) $hooks->applyFilters(HookPoints::CMS_THE_CONTENT, $html, $ctx);
        } catch (\Throwable $e) {
            // fallback: run shortcodes only
            return (string) app(ShortcodeParser::class)->render($html, $ctx);
        }
    }
}