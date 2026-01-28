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
        $this->bumpMenuLocations();
    }

    public function deleted(MenuItem $item): void
    {
        $this->bumpMenuLocations();
    }

    public function assignmentSaved(MenuAssignment $assignment): void
    {
        $this->versions->bump('menu_location', (string) $assignment->location_key);
    }

    protected function bumpMenuLocations(): void
    {
        // Safe approach: bump all existing locations that have assignments
        $keys = MenuAssignment::query()->pluck('location_key')->unique()->values();
        foreach ($keys as $k) {
            $this->versions->bump('menu_location', (string) $k);
        }
    }
}