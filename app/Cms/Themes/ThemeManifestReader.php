<?php

namespace App\Cms\Themes;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use RuntimeException;

final class ThemeManifestReader
{
    public function __construct(
        private readonly Filesystem $fs,
    ) {
    }

    public function read(string $themeDir): ThemeManifest
    {
        $path = rtrim($themeDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'theme.json';
        if (!$this->fs->exists($path)) {
            throw new RuntimeException("theme.json not found in: {$themeDir}");
        }

        $json = json_decode($this->fs->get($path), true);
        if (!is_array($json)) {
            throw new RuntimeException("Invalid theme.json in: {$themeDir}");
        }

        foreach (['name', 'slug', 'version'] as $key) {
            if (blank($json[$key] ?? null)) {
                throw new RuntimeException("theme.json missing required field: {$key}");
            }
        }

        $slug = Str::lower((string) $json['slug']);
        if (!preg_match(config('cms.theme_slug_regex'), $slug)) {
            throw new RuntimeException("Invalid theme slug: {$slug}");
        }

        return new ThemeManifest(
            name: (string) $json['name'],
            slug: $slug,
            version: (string) $json['version'],
            author: Arr::get($json, 'author'),
            description: Arr::get($json, 'description'),
            parent: Arr::get($json, 'parent'),
            templates: Arr::get($json, 'templates', []),
            menus: Arr::get($json, 'menus', []),
            sidebars: Arr::get($json, 'sidebars', []),
            assets: Arr::get($json, 'assets', []),
            raw: $json,
        );
    }
}