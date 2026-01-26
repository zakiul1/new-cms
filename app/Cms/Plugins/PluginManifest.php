<?php

namespace App\Cms\Plugins;

final class PluginManifest
{
    public function __construct(
        public readonly string $name,
        public readonly string $slug,
        public readonly string $version,
        public readonly string $bootstrap = 'bootstrap.php',
        public readonly array $assets = [],
        public readonly array $raw = [],
    ) {}

    public static function fromArray(array $data, string $fallbackSlug): self
    {
        $slug = (string)($data['slug'] ?? $fallbackSlug);

        return new self(
            name: (string)($data['name'] ?? $slug),
            slug: $slug,
            version: (string)($data['version'] ?? '0.0.0'),
            bootstrap: (string)($data['bootstrap'] ?? 'bootstrap.php'),
            assets: is_array($data['assets'] ?? null) ? $data['assets'] : [],
            raw: $data,
        );
    }
}
