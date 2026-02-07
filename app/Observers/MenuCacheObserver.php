<?php

namespace App\Observers;

use App\Cms\Core\CmsCacheVersions;
use App\Models\MenuAssignment;
use App\Models\MenuItem;

class MenuCacheObserver
{
    public function __construct(protected CmsCacheVersions $versions)
    {
    }

    public function saved(MenuItem $item): void
    {
        $this->bumpLocationsForMenuId((int) $item->menu_id);
    }

    public function deleted(MenuItem $item): void
    {
        $this->bumpLocationsForMenuId((int) $item->menu_id);
    }

    public function assignmentSaved(MenuAssignment $assignment): void
    {
        $this->versions->bump('menu_location', (string) $assignment->location_key);
    }

    /**
     * ✅ Admin/utility: bump ALL menu locations (safe)
     */
    public function bumpAllMenuLocations(): void
    {
        $keys = MenuAssignment::query()
            ->pluck('location_key')
            ->unique()
            ->values();

        foreach ($keys as $k) {
            $this->versions->bump('menu_location', (string) $k);
        }
    }

    /**
     * Bump only the locations that point to a specific menu.
     */
    protected function bumpLocationsForMenuId(int $menuId): void
    {
        if ($menuId <= 0) {
            // fallback safety
            $this->bumpAllMenuLocations();
            return;
        }

        $keys = MenuAssignment::query()
            ->where('menu_id', $menuId)
            ->pluck('location_key')
            ->unique()
            ->values();

        // if nothing is assigned, no need to bump, but keep safety fallback
        if ($keys->isEmpty()) {
            return;
        }

        foreach ($keys as $k) {
            $this->versions->bump('menu_location', (string) $k);
        }
    }
}