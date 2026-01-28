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
        // bump all areas where this widget is placed
        $areaKeys = WidgetPlacement::query()
            ->where('widget_id', $widget->id)
            ->pluck('widget_area_key')
            ->unique()
            ->values();

        foreach ($areaKeys as $k) {
            $this->versions->bump('widget_area', (string) $k);
        }
    }

    public function widgetDeleted(Widget $widget): void
    {
        $this->widgetSaved($widget);
    }
}