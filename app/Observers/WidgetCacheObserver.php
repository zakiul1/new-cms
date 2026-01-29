<?php

namespace App\Observers;

use App\Cms\Core\CmsCacheVersions;
use App\Models\Widget;
use App\Models\WidgetPlacement;

class WidgetCacheObserver
{
    public function __construct(protected CmsCacheVersions $versions)
    {
    }

    public function placementSaved(WidgetPlacement $placement): void
    {
        $this->versions->bump('widget_area', (string) $placement->widget_area_key);
    }

    public function placementDeleted(WidgetPlacement $placement): void
    {
        $this->versions->bump('widget_area', (string) $placement->widget_area_key);
    }

    public function widgetSaved(Widget $widget): void
    {
        $this->bumpAreasForWidgetId((int) $widget->id);
    }

    public function widgetDeleted(Widget $widget): void
    {
        $this->bumpAreasForWidgetId((int) $widget->id);
    }

    /**
     * ✅ Admin/utility: bump ALL widget areas (safe)
     */
    public function bumpAllWidgetAreas(): void
    {
        $keys = WidgetPlacement::query()
            ->pluck('widget_area_key')
            ->unique()
            ->values();

        foreach ($keys as $k) {
            $this->versions->bump('widget_area', (string) $k);
        }
    }

    protected function bumpAreasForWidgetId(int $widgetId): void
    {
        if ($widgetId <= 0) {
            $this->bumpAllWidgetAreas();
            return;
        }

        $areaKeys = WidgetPlacement::query()
            ->where('widget_id', $widgetId)
            ->pluck('widget_area_key')
            ->unique()
            ->values();

        foreach ($areaKeys as $k) {
            $this->versions->bump('widget_area', (string) $k);
        }
    }
}