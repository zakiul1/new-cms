<?php

namespace App\Cms\Content\Shortcodes;

final class ShortcodeParser
{
    public function __construct(private readonly ShortcodeRegistry $registry) {}

    public function render(string $input, array $ctx = []): string
    {
        if ($input === '' || $this->registry->all() === []) {
            return $input;
        }

        // Escape: [[tag]] => [tag]
        $input = preg_replace('/\[\[([a-zA-Z0-9_-]+)([^\]]*)\]\]/', '[$1$2]', $input) ?? $input;

        // Enclosing shortcodes: [tag ...]content[/tag]
        $patternEnclosing = '/\[([a-zA-Z0-9_-]+)([^\]]*)\](.*?)\[\/\1\]/s';
        $input = preg_replace_callback($patternEnclosing, function ($m) use ($ctx) {
            $tag = strtolower($m[1]);
            if (!$this->registry->has($tag)) {
                return $m[0];
            }

            $attrs = $this->parseAttrs($m[2] ?? '');
            $content = $m[3] ?? '';

            return $this->registry->run($tag, $attrs, $content, $ctx);
        }, $input) ?? $input;

        // Self-closing / single: [tag ...] or [tag ... /]
        $patternSingle = '/\[([a-zA-Z0-9_-]+)([^\]]*)\]/';
        $input = preg_replace_callback($patternSingle, function ($m) use ($ctx) {
            $tag = strtolower($m[1]);
            if (!$this->registry->has($tag)) {
                return $m[0];
            }

            $attrs = $this->parseAttrs($m[2] ?? '');

            // If user wrote [/tag] already handled above; here content is null
            return $this->registry->run($tag, $attrs, null, $ctx);
        }, $input) ?? $input;

        return $input;
    }

    /** @return array<string, string|bool> */
    private function parseAttrs(string $raw): array
    {
        $raw = trim($raw);
        if ($raw === '') {
            return [];
        }

        // Remove trailing slash from [tag ... /]
        $raw = preg_replace('/\s*\/\s*$/', '', $raw) ?? $raw;

        $attrs = [];

        // key="value" | key='value' | key=value | key (boolean)
        preg_match_all(
            '/(\w+)(?:\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|([^\s"\']+)))?/',
            $raw,
            $matches,
            PREG_SET_ORDER
        );

        foreach ($matches as $m) {
            $key = strtolower($m[1]);
            $val = $m[2] ?? $m[3] ?? $m[4] ?? null;

            $attrs[$key] = ($val === null) ? true : (string) $val;
        }

        return $attrs;
    }
}
