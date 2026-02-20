<?php

namespace App\Observers;

use App\Cms\Core\CmsCacheVersions;
use App\Models\MenuAssignment;

class MenuAssignmentCacheObserver
{
    public function __construct(private CmsCacheVersions $versions)
    {
    }

    public function saved(MenuAssignment $assignment): void
    {
        $this->versions->bump('menu_location', (string) $assignment->location_key);
    }

    public function deleted(MenuAssignment $assignment): void
    {
        $this->versions->bump('menu_location', (string) $assignment->location_key);
    }
}