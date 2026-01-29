<?php

namespace App\Cms\Menus;

use App\Cms\Core\CmsCacheVersions;
use App\Cms\Hooks\Hooks;
use App\Models\MenuAssignment;
use App\Models\MenuItem;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class MenuRenderer
{
    public function __construct(
        protected Hooks $hooks,
        protected CmsCacheVersions $versions,
    ) {
    }

    public function renderLocation(string $locationKey, array $ctx = []): string
    {
        $ver = $this->versions->get('menu_location', $locationKey);

        // ✅ global render version (theme/plugins/customizer publish)
        $renderVer = $this->versions->getRender();

        $cacheKey = "cms:menu:location:{$locationKey}:v{$ver}:r{$renderVer}";

        return (string) Cache::remember($cacheKey, now()->addMinutes(30), function () use ($locationKey, $ctx) {
            $assignment = MenuAssignment::query()
                ->with('menu')
                ->where('location_key', $locationKey)
                ->first();

            if (!$assignment?->menu) {
                return '';
            }

            $items = MenuItem::query()
                ->where('menu_id', $assignment->menu->id)
                ->where('is_enabled', true)
                ->orderBy('parent_id')
                ->orderBy('sort_order')
                ->get();

            $tree = $this->buildTree($items);

            $html = $this->renderTree($tree, $ctx);

            // allow plugins/theme to filter final html
            $html = $this->hooks->applyFilters('cms.menu.html', $html, $locationKey, $ctx);

            return $html;
        });
    }

    /** @param Collection<int, MenuItem> $items */
    protected function buildTree(Collection $items): array
    {
        $byParent = [];
        foreach ($items as $item) {
            $pid = $item->parent_id ?: 0;
            $byParent[$pid][] = $item;
        }

        $walk = function (int $parentId) use (&$walk, &$byParent): array {
            $children = $byParent[$parentId] ?? [];
            $out = [];
            foreach ($children as $child) {
                $out[] = [
                    'item' => $child,
                    'children' => $walk((int) $child->id),
                ];
            }
            return $out;
        };

        return $walk(0);
    }

    protected function renderTree(array $tree, array $ctx): string
    {
        if (empty($tree)) {
            return '';
        }

        $html = '<ul class="cms-menu">';

        foreach ($tree as $node) {
            /** @var MenuItem $item */
            $item = $node['item'];

            // filter each item
            $item = $this->hooks->applyFilters('cms.menu.item', $item, $ctx);
            if (!($item instanceof MenuItem)) {
                continue;
            }

            $data = is_array($item->data) ? $item->data : [];

            // optional visibility (premium-ready)
            if (!$this->passesVisibility($data['visibility'] ?? null, $ctx)) {
                continue;
            }

            $html .= '<li class="cms-menu__item">';

            $html .= $this->renderItem($item, $data, $ctx);

            if (!empty($node['children'])) {
                $html .= $this->renderTree($node['children'], $ctx);
            }

            $html .= '</li>';
        }

        $html .= '</ul>';

        return $html;
    }

    protected function renderItem(MenuItem $item, array $data, array $ctx): string
    {
        $type = $item->type;

        if ($type === 'separator') {
            return '<span class="cms-menu__sep"></span>';
        }

        if ($type === 'heading') {
            return '<span class="cms-menu__heading">' . e((string) ($item->label ?? '')) . '</span>';
        }

        $label = (string) ($item->label ?? '');
        $url = (string) ($item->url ?? '#');

        $target = (string) ($data['target'] ?? '');
        $relParts = [];

        if (!empty($data['nofollow'])) {
            $relParts[] = 'nofollow';
        }
        if (!empty($data['sponsored'])) {
            $relParts[] = 'sponsored';
        }
        if (!empty($data['ugc'])) {
            $relParts[] = 'ugc';
        }

        $targetAttr = $target ? ' target="' . e($target) . '"' : '';
        $relAttr = !empty($relParts) ? ' rel="' . e(implode(' ', $relParts)) . '"' : '';

        return '<a class="cms-menu__link" href="' . e($url) . '"' . $targetAttr . $relAttr . '>' . e($label) . '</a>';
    }

    protected function passesVisibility(?array $rules, array $ctx): bool
    {
        if (empty($rules)) {
            return true;
        }

        // MVP: only basic auth rules; extend later without DB changes
        $auth = $rules['auth'] ?? null;

        if ($auth === 'guest') {
            return auth()->guest();
        }

        if ($auth === 'logged_in') {
            return auth()->check();
        }

        return true;
    }
}