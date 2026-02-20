<?php

namespace App\Observers;

use App\Cms\Core\CmsCacheVersions;
use App\Models\MenuAssignment;
use App\Models\MenuItem;

class MenuItemCacheObserver
{
    public function __construct(private CmsCacheVersions $versions)
    {
    }

    public function saved(MenuItem $item): void
    {
        $this->bumpLocationsForMenu((int) $item->menu_id);
    }

    public function deleted(MenuItem $item): void
    {
        $this->bumpLocationsForMenu((int) $item->menu_id);
    }

    private function bumpLocationsForMenu(int $menuId): void
    {
        $locationKeys = MenuAssignment::where('menu_id', $menuId)
            ->pluck('location_key')
            ->unique();

        foreach ($locationKeys as $locationKey) {
            $this->versions->bump('menu_location', (string) $locationKey);
        }
    }
}