<?php

namespace App\Cms\Themes;

use App\Cms\Core\Settings;

final class ThemeOptions
{
    public function __construct(private Settings $settings)
    {
    }

    private function groupFor(string $themeSlug): string
    {
        return 'theme:' . $themeSlug;
    }

    public function get(string $themeSlug, string $key, mixed $default = null): mixed
    {
        return $this->settings->get($key, $default, $this->groupFor($themeSlug));
    }

    public function set(string $themeSlug, string $key, mixed $value): void
    {
        $this->settings->set($key, $value, $this->groupFor($themeSlug));
    }

    /** @return array<string,mixed> */
    public function all(string $themeSlug): array
    {
        return $this->settings->all($this->groupFor($themeSlug));
    }
}