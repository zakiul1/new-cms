<?php

namespace App\Cms\Content\Shortcodes;

final class ShortcodeRegistry
{
    /** @var array<string, callable(array $attrs, ?string $content, array $ctx): string> */
    private array $map = [];

    /**
     * Metadata store for each shortcode tag.
     *
     * Shape:
     * [
     *   'description' => string,
     *   'params' => array<int, array{name:string,type?:string,default?:mixed,desc?:string}>,
     *   'examples' => array<int, string>,
     *   'group' => string,
     * ]
     *
     * @var array<string, array>
     */
    private array $meta = [];

    public function register(string $tag, callable $handler): void
    {
        $tag = strtolower(trim($tag));
        if ($tag === '') {
            return;
        }

        $this->map[$tag] = $handler;

        // Ensure meta exists even if none provided (so it shows in UI)
        $this->meta[$tag] = $this->meta[$tag] ?? [
            'description' => '',
            'params' => [],
            'examples' => [],
            'group' => 'General',
        ];
    }

    /**
     * Register shortcode with documentation metadata.
     *
     * Example:
     * $registry->registerWithMeta('button', $handler, [
     *   'group' => 'Core',
     *   'description' => 'Renders a button link.',
     *   'params' => [
     *      ['name'=>'url','type'=>'string','default'=>'#','desc'=>'Button URL'],
     *   ],
     *   'examples' => ['[button url="..."]'],
     * ]);
     */
    public function registerWithMeta(string $tag, callable $handler, array $meta): void
    {
        $this->register($tag, $handler);

        $tag = strtolower(trim($tag));

        $current = $this->meta[$tag] ?? [
            'description' => '',
            'params' => [],
            'examples' => [],
            'group' => 'General',
        ];

        $incoming = [
            'description' => (string) ($meta['description'] ?? $current['description'] ?? ''),
            'params' => is_array($meta['params'] ?? null) ? $meta['params'] : ($current['params'] ?? []),
            'examples' => is_array($meta['examples'] ?? null) ? $meta['examples'] : ($current['examples'] ?? []),
            'group' => (string) ($meta['group'] ?? $current['group'] ?? 'General'),
        ];

        $this->meta[$tag] = array_merge($current, $incoming);
    }

    /**
     * Update meta for an already-registered shortcode.
     */
    public function setMeta(string $tag, array $meta): void
    {
        $tag = strtolower(trim($tag));
        if ($tag === '') {
            return;
        }

        $current = $this->meta[$tag] ?? [
            'description' => '',
            'params' => [],
            'examples' => [],
            'group' => 'General',
        ];

        $incoming = [
            'description' => array_key_exists('description', $meta) ? (string) $meta['description'] : ($current['description'] ?? ''),
            'params' => array_key_exists('params', $meta) && is_array($meta['params']) ? $meta['params'] : ($current['params'] ?? []),
            'examples' => array_key_exists('examples', $meta) && is_array($meta['examples']) ? $meta['examples'] : ($current['examples'] ?? []),
            'group' => array_key_exists('group', $meta) ? (string) $meta['group'] : ($current['group'] ?? 'General'),
        ];

        $this->meta[$tag] = array_merge($current, $incoming);
    }

    public function metaFor(string $tag): array
    {
        $tag = strtolower(trim($tag));

        return $this->meta[$tag] ?? [
            'description' => '',
            'params' => [],
            'examples' => [],
            'group' => 'General',
        ];
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

    /**
     * Return all shortcode definitions for UI listing.
     *
     * @return array<int, array{tag:string, description:string, params:array, examples:array, group:string}>
     */
    public function definitions(): array
    {
        $out = [];

        foreach (array_keys($this->map) as $tag) {
            $m = $this->meta[$tag] ?? [
                'description' => '',
                'params' => [],
                'examples' => [],
                'group' => 'General',
            ];

            $out[] = [
                'tag' => $tag,
                'description' => (string) ($m['description'] ?? ''),
                'params' => is_array($m['params'] ?? null) ? $m['params'] : [],
                'examples' => is_array($m['examples'] ?? null) ? $m['examples'] : [],
                'group' => (string) ($m['group'] ?? 'General'),
            ];
        }

        // Sort A-Z
        usort($out, fn($a, $b) => strcmp($a['tag'], $b['tag']));

        return $out;
    }
}