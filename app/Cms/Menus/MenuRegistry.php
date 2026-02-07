<?php

namespace App\Cms\Menus;

use App\Models\MenuLocation;
use Illuminate\Support\Facades\Schema;

class MenuRegistry
{
    /** @var array<string, array{label:string, theme_slug:?string}> */
    protected array $locations = [];

    public function register(string $key, string $label, ?string $themeSlug = null): void
    {
        $this->locations[$key] = ['label' => $label, 'theme_slug' => $themeSlug];

        // ✅ During migrations / first install, tables may not exist yet.
        if (!Schema::hasTable('menu_locations')) {
            return;
        }

        MenuLocation::query()->updateOrCreate(
            ['key' => $key],
            ['label' => $label, 'theme_slug' => $themeSlug],
        );
    }

    /** @return array<string, array{label:string, theme_slug:?string}> */
    public function all(): array
    {
        return $this->locations;
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->locations);
    }
}