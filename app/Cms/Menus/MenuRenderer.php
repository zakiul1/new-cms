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

        $url = $this->normalizeMenuUrl($url);

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

    /**
     * Normalize menu item URLs to match CMS trailing-slash standard.
     * - Keeps external URLs untouched
     * - Keeps mailto/tel/javascript untouched
     * - Preserves query + fragment
     * - Converts "/home" -> site root
     * - Adds trailing slash for internal/relative paths (excluding file-like paths)
     */
    protected function normalizeMenuUrl(string $url): string
    {
        $url = trim($url);

        if ($url === '' || $url === '#') {
            return $url;
        }

        // leave special schemes untouched
        if (preg_match('#^(mailto:|tel:|javascript:)#i', $url)) {
            return $url;
        }

        $parsed = parse_url($url);

        // If parse_url fails, treat it as a relative path string
        if ($parsed === false) {
            $parsed = ['path' => $url];
        }

        $scheme = $parsed['scheme'] ?? null;
        $host = $parsed['host'] ?? null;

        $path = $parsed['path'] ?? '';
        if ($path === '' && !isset($scheme) && !isset($host)) {
            // e.g. "home" without slash
            $path = $url;
        }

        $path = '/' . ltrim((string) $path, '/');

        // normalize HOME -> root
        if ($path === '/home') {
            $out = url('/');
            if (!empty($parsed['query'])) {
                $out .= '?' . $parsed['query'];
            }
            if (!empty($parsed['fragment'])) {
                $out .= '#' . $parsed['fragment'];
            }
            return $out;
        }

        // Determine if it's an internal URL:
        // - relative (no scheme/host)
        // - OR absolute with same host as app.url (common when stored as full URL)
        $isRelative = !isset($scheme) && !isset($host);

        $isSameHost = false;
        if (isset($host)) {
            $appHost = parse_url((string) url('/'), PHP_URL_HOST);
            if (is_string($appHost) && $appHost !== '' && strcasecmp($host, $appHost) === 0) {
                $isSameHost = true;
            }
        }

        // Only normalize trailing slash for internal URLs
        if ($isRelative || $isSameHost) {
            // skip file-like paths (sitemap.xml, .css, images, etc.)
            $lastSeg = basename($path);
            if (!str_contains($lastSeg, '.')) {
                $path = rtrim($path, '/') . '/';
            }
        }

        // Rebuild URL
        if ($isRelative) {
            $out = $path;
        } else {
            $out = '';
            $out .= $scheme ? ($scheme . '://') : '';
            // user:pass@
            if (!empty($parsed['user'])) {
                $out .= $parsed['user'];
                if (!empty($parsed['pass'])) {
                    $out .= ':' . $parsed['pass'];
                }
                $out .= '@';
            }
            $out .= $host ?? '';
            if (!empty($parsed['port'])) {
                $out .= ':' . $parsed['port'];
            }
            $out .= $path;
        }

        if (!empty($parsed['query'])) {
            $out .= '?' . $parsed['query'];
        }
        if (!empty($parsed['fragment'])) {
            $out .= '#' . $parsed['fragment'];
        }

        return $out;
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