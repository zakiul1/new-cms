<?php

namespace App\Cms\Widgets;

use App\Models\WidgetArea;
use Illuminate\Support\Facades\Schema;

class SidebarRegistry
{
    /** @var array<string, array{label:string, theme_slug:?string}> */
    protected array $areas = [];

    public function register(string $key, string $label, ?string $themeSlug = null): void
    {
        $this->areas[$key] = ['label' => $label, 'theme_slug' => $themeSlug];

        // ✅ During migrations / first install, tables may not exist yet.
        if (!Schema::hasTable('widget_areas')) {
            return;
        }

        WidgetArea::query()->updateOrCreate(
            ['key' => $key],
            ['label' => $label, 'theme_slug' => $themeSlug],
        );
    }

    /** @return array<string, array{label:string, theme_slug:?string}> */
    public function all(): array
    {
        return $this->areas;
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->areas);
    }
}