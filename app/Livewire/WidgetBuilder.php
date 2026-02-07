<?php

namespace App\Livewire;

use App\Cms\Widgets\WidgetRegistry;
use App\Models\Widget;
use App\Models\WidgetArea;
use App\Models\WidgetPlacement;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

class WidgetBuilder extends Component
{
    public ?string $activeAreaKey = null;

    // Create widget
    public string $newWidgetType = 'text';
    public string $newWidgetTitle = '';

    // Select placement to edit
    public ?int $activePlacementId = null;

    // Editable state
    public array $placements = []; // by placement_id: overrides/visibility/widget_id
    public array $widgets = [];    // by widget_id: title/is_enabled/settings/type
    public array $savedAt = [];    // timestamps by placement_id or widget_id

    // Optional: search existing widgets to place
    public string $searchExisting = '';
    public ?int $existingWidgetId = null;

    public function mount(WidgetRegistry $registry): void
    {
        // Default to first area in DB
        $this->activeAreaKey = WidgetArea::query()->orderBy('label')->value('key');

        // Default type to first registered option if possible
        $opts = $registry->options();
        if (!empty($opts)) {
            $this->newWidgetType = array_key_first($opts);
        }

        $this->reload();
    }

    public function render(WidgetRegistry $registry)
    {
        return view('livewire.widget-builder', [
            'areas' => WidgetArea::query()->orderBy('label')->get(),
            'widgetTypeOptions' => $registry->options(),
            'existingWidgets' => $this->queryExistingWidgets(),
        ]);
    }

    // --------------------
    // Area actions
    // --------------------

    public function selectArea(string $key): void
    {
        $this->activeAreaKey = $key;
        $this->activePlacementId = null;
        $this->reload();
    }

    // --------------------
    // Create / place widgets
    // --------------------

    public function createAndPlaceWidget(): void
    {
        if (!$this->activeAreaKey)
            return;

        $type = trim($this->newWidgetType);
        $title = trim($this->newWidgetTitle);

        if ($type === '') {
            $this->addError('newWidgetType', 'Widget type is required.');
            return;
        }

        DB::transaction(function () use ($type, $title) {
            $widget = Widget::query()->create([
                'type' => $type,
                'title' => $title !== '' ? $title : null,
                'is_enabled' => true,
                'settings' => [],
            ]);

            $max = (int) WidgetPlacement::query()
                ->where('widget_area_key', $this->activeAreaKey)
                ->max('sort_order');

            $placement = WidgetPlacement::query()->create([
                'widget_area_key' => $this->activeAreaKey,
                'widget_id' => $widget->id,
                'sort_order' => $max + 1,
                'overrides' => null,
                'visibility' => null,
            ]);

            $this->activePlacementId = (int) $placement->id;
        });

        $this->newWidgetTitle = '';
        $this->reload();
    }

    public function placeExistingWidget(): void
    {
        if (!$this->activeAreaKey)
            return;
        if (!$this->existingWidgetId)
            return;

        $widgetId = (int) $this->existingWidgetId;

        DB::transaction(function () use ($widgetId) {
            $exists = Widget::query()->whereKey($widgetId)->exists();
            if (!$exists)
                return;

            $max = (int) WidgetPlacement::query()
                ->where('widget_area_key', $this->activeAreaKey)
                ->max('sort_order');

            $placement = WidgetPlacement::query()->create([
                'widget_area_key' => $this->activeAreaKey,
                'widget_id' => $widgetId,
                'sort_order' => $max + 1,
                'overrides' => null,
                'visibility' => null,
            ]);

            $this->activePlacementId = (int) $placement->id;
        });

        $this->existingWidgetId = null;
        $this->reload();
    }

    // --------------------
    // Reorder (drag/drop)
    // --------------------

    /** Called by JS after drag/drop. Example payload: [12, 9, 15] placement IDs */
    public function reorder(array $orderedPlacementIds): void
    {
        if (!$this->activeAreaKey)
            return;

        $ids = array_values(array_filter(array_map('intval', $orderedPlacementIds), fn($v) => $v > 0));
        if ($ids === [])
            return;

        DB::transaction(function () use ($ids) {
            $sort = 1;
            foreach ($ids as $pid) {
                WidgetPlacement::query()
                    ->where('widget_area_key', $this->activeAreaKey)
                    ->whereKey($pid)
                    ->update(['sort_order' => $sort++]);
            }
        });

        $this->reload();
    }

    // --------------------
    // Select / edit / save
    // --------------------

    public function selectPlacement(int $placementId): void
    {
        $this->activePlacementId = $placementId;
    }

    public function removePlacement(int $placementId): void
    {
        if (!$this->activeAreaKey)
            return;

        DB::transaction(function () use ($placementId) {
            WidgetPlacement::query()
                ->where('widget_area_key', $this->activeAreaKey)
                ->whereKey($placementId)
                ->delete();
        });

        if ($this->activePlacementId === $placementId) {
            $this->activePlacementId = null;
        }

        $this->reload();
    }

    public function deleteWidget(int $widgetId): void
    {
        DB::transaction(function () use ($widgetId) {
            Widget::query()->whereKey($widgetId)->delete();
        });

        $this->activePlacementId = null;
        $this->reload();
    }

    public function savePlacement(int $placementId): void
    {
        if (!$this->activeAreaKey)
            return;

        $row = $this->placements[$placementId] ?? null;
        if (!is_array($row))
            return;

        // visibility normalize
        $vis = $row['visibility'] ?? [];
        if (!is_array($vis))
            $vis = [];

        $vis = array_merge([
            'auth' => 'any',
            'roles' => [],
        ], $vis);

        if (isset($vis['roles_csv']) && is_string($vis['roles_csv'])) {
            $roles = array_filter(array_map('trim', explode(',', $vis['roles_csv'])));
            $vis['roles'] = array_values($roles);
            unset($vis['roles_csv']);
        }

        $auth = $vis['auth'] ?? 'any';
        if (!in_array($auth, ['any', 'guest', 'auth'], true)) {
            $auth = 'any';
        }
        $vis['auth'] = $auth;

        $storeVisibility = $vis;
        if (($storeVisibility['auth'] ?? 'any') === 'any' && ($storeVisibility['roles'] ?? []) === []) {
            $storeVisibility = null;
        }

        // overrides normalize
        $over = $row['overrides'] ?? [];
        if (!is_array($over))
            $over = [];

        $over = array_merge([
            'title' => null,
            'hide_title' => false,
            'css_class' => null,
            'wrapper_tag' => null,
        ], $over);

        $over['hide_title'] = (bool) ($over['hide_title'] ?? false);

        $tag = $over['wrapper_tag'] ?? null;
        if ($tag !== null && !in_array($tag, ['div', 'aside', 'section'], true)) {
            $tag = null;
        }
        $over['wrapper_tag'] = $tag;

        // remove empties
        $storeOverrides = $over;
        $allEmpty = ($storeOverrides['title'] === null || trim((string) $storeOverrides['title']) === '')
            && ($storeOverrides['hide_title'] === false)
            && ($storeOverrides['css_class'] === null || trim((string) $storeOverrides['css_class']) === '')
            && ($storeOverrides['wrapper_tag'] === null);

        if ($allEmpty) {
            $storeOverrides = null;
        } else {
            // normalize blanks to null
            if (is_string($storeOverrides['title']) && trim($storeOverrides['title']) === '')
                $storeOverrides['title'] = null;
            if (is_string($storeOverrides['css_class']) && trim($storeOverrides['css_class']) === '')
                $storeOverrides['css_class'] = null;
        }

        WidgetPlacement::query()
            ->where('widget_area_key', $this->activeAreaKey)
            ->whereKey($placementId)
            ->update([
                'visibility' => $storeVisibility,
                'overrides' => $storeOverrides,
            ]);

        $this->savedAt['p:' . $placementId] = time();
    }

    public function saveWidget(int $widgetId): void
    {
        $row = $this->widgets[$widgetId] ?? null;
        if (!is_array($row))
            return;

        $settings = $row['settings'] ?? [];
        if (!is_array($settings))
            $settings = [];

        Widget::query()
            ->whereKey($widgetId)
            ->update([
                'title' => isset($row['title']) && trim((string) $row['title']) !== '' ? trim((string) $row['title']) : null,
                'is_enabled' => (bool) ($row['is_enabled'] ?? true),
                'settings' => $settings ?: null,
            ]);

        $this->savedAt['w:' . $widgetId] = time();
    }

    // --------------------
    // Internals
    // --------------------

    private function reload(): void
    {
        $this->placements = [];
        $this->widgets = [];

        if ($this->activeAreaKey) {
            $rows = WidgetPlacement::query()
                ->with('widget')
                ->where('widget_area_key', $this->activeAreaKey)
                ->orderBy('sort_order')
                ->get();

            foreach ($rows as $p) {
                $wid = (int) $p->widget_id;

                $this->placements[(int) $p->id] = [
                    'widget_id' => $wid,
                    'visibility' => array_merge(['auth' => 'any', 'roles' => [], 'roles_csv' => ''], is_array($p->visibility) ? $p->visibility : []),
                    'overrides' => array_merge(['title' => null, 'hide_title' => false, 'css_class' => null, 'wrapper_tag' => null], is_array($p->overrides) ? $p->overrides : []),
                ];

                // roles_csv helper
                $roles = $this->placements[(int) $p->id]['visibility']['roles'] ?? [];
                if (!is_array($roles))
                    $roles = [];
                $this->placements[(int) $p->id]['visibility']['roles_csv'] = implode(', ', array_values(array_filter(array_map('trim', $roles))));

                if ($p->widget) {
                    $this->widgets[$wid] = [
                        'type' => (string) $p->widget->type,
                        'title' => (string) ($p->widget->title ?? ''),
                        'is_enabled' => (bool) $p->widget->is_enabled,
                        'settings' => is_array($p->widget->settings) ? $p->widget->settings : [],
                    ];
                }
            }
        }

        // re-init Sortable
        $this->dispatch('widget-builder-init');
    }

    private function queryExistingWidgets()
    {
        $q = Widget::query()->orderByDesc('id');

        $s = trim($this->searchExisting);
        if ($s !== '') {
            $q->where(function ($qq) use ($s) {
                $qq->where('title', 'like', "%{$s}%")
                    ->orWhere('type', 'like', "%{$s}%");
            });
        }

        return $q->limit(50)->get();
    }
}