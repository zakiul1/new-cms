<?php

namespace App\Cms\Menus;

use App\Cms\Core\CmsCacheVersions;
use App\Models\MenuAssignment;

class MenuCacheInvalidator
{
    public function __construct(private CmsCacheVersions $versions)
    {
    }

    /**
     * Bust cache for one menu location key (header, footer, etc.)
     */
    public function bumpLocation(?string $locationKey): void
    {
        $locationKey = $locationKey !== null ? trim($locationKey) : null;
        if ($locationKey === null || $locationKey === '') {
            return;
        }

        $this->versions->bump('menu_location', $locationKey);
    }

    /**
     * Bust cache for all locations that currently point to a given menu.
     */
    public function bumpForMenu(int $menuId): void
    {
        if ($menuId <= 0) {
            return;
        }

        $keys = MenuAssignment::query()
            ->where('menu_id', $menuId)
            ->pluck('location_key')
            ->unique()
            ->filter(fn($k) => is_string($k) && trim($k) !== '');

        foreach ($keys as $k) {
            $this->versions->bump('menu_location', (string) $k);
        }
    }

    /**
     * Bust cache for old+new location keys (useful when re-assigning).
     */
    public function bumpOldAndNew(?string $oldKey, ?string $newKey): void
    {
        $this->bumpLocation($oldKey);
        $this->bumpLocation($newKey);
    }
}