<?php

namespace App\Cms\Content\Shortcodes;

final class ShortcodeRegistry
{
    /** @var array<string, callable(array $attrs, ?string $content, array $ctx): string> */
    private array $map = [];

    public function register(string $tag, callable $handler): void
    {
        $tag = strtolower(trim($tag));
        if ($tag === '') {
            return;
        }

        $this->map[$tag] = $handler;
    }

    public function has(string $tag): bool
    {
        return array_key_exists(strtolower($tag), $this->map);
    }

    public function run(string $tag, array $attrs, ?string $content, array $ctx = []): string
    {
        $tag = strtolower($tag);

        if (!isset($this->map[$tag])) {
            // Unknown shortcode -> leave as-is (WP-ish behavior can vary)
            return '';
        }

        return (string) call_user_func($this->map[$tag], $attrs, $content, $ctx);
    }

    /** @return array<string, callable> */
    public function all(): array
    {
        return $this->map;
    }
}
