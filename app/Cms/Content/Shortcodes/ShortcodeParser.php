<?php

namespace App\Cms\Content\Shortcodes;

final class ShortcodeParser
{
    public function __construct(private readonly ShortcodeRegistry $registry)
    {
    }

    public function render(string $input, array $ctx = []): string
    {
        if ($input === '' || $this->registry->all() === []) {
            return $input;
        }

        // Escape:
        // [[tag]] => [tag]
        // [[tag:4]] => [tag:4]
        $input = preg_replace_callback('/\[\[([a-zA-Z0-9_-]+)(?::(\d+))?([^\]]*)\]\]/', function ($m) {
            $name = $m[1] ?? '';
            $num = isset($m[2]) && $m[2] !== '' ? ':' . $m[2] : '';
            $rest = $m[3] ?? '';
            return '[' . $name . $num . $rest . ']';
        }, $input) ?? $input;

        // Enclosing shortcodes:
        // [tag ...]content[/tag]
        // [tag:4 ...]content[/tag]
        $patternEnclosing = '/\[([a-zA-Z0-9_-]+)(?::(\d+))?([^\]]*)\](.*?)\[\/\1\]/s';
        $input = preg_replace_callback($patternEnclosing, function ($m) use ($ctx) {
            $tag = strtolower($m[1]);
            if (!$this->registry->has($tag)) {
                return $m[0];
            }

            $num = isset($m[2]) && $m[2] !== '' ? (int) $m[2] : null;

            $attrs = $this->parseAttrs($m[3] ?? '');
            if ($num !== null && !isset($attrs['number'])) {
                $attrs['number'] = (string) $num;
            }

            $content = $m[4] ?? '';

            return $this->registry->run($tag, $attrs, $content, $ctx);
        }, $input) ?? $input;

        // Self-closing / single:
        // [tag ...] or [tag ... /]
        // [tag:4] or [tag:4 sep=", "]
        $patternSingle = '/\[([a-zA-Z0-9_-]+)(?::(\d+))?([^\]]*)\]/';
        $input = preg_replace_callback($patternSingle, function ($m) use ($ctx) {
            $tag = strtolower($m[1]);
            if (!$this->registry->has($tag)) {
                return $m[0];
            }

            $num = isset($m[2]) && $m[2] !== '' ? (int) $m[2] : null;

            $attrs = $this->parseAttrs($m[3] ?? '');
            if ($num !== null && !isset($attrs['number'])) {
                $attrs['number'] = (string) $num;
            }

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