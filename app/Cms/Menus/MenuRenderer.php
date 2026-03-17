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

    protected function renderTree(array $tree, array $ctx, int $depth = 0, array $options = []): string
    {
        if (empty($tree)) {
            return '';
        }

        $classList = ['cms-menu', 'cms-menu--depth-' . $depth];

        if ($depth === 0) {
            $classList[] = 'menu-root';
        }

        if (($options['is_mega_panel'] ?? false) === true) {
            $classList[] = 'mega-menu-panel';
            $classList[] = 'has-mega-menu';
        }

        if (($options['is_mega_column_list'] ?? false) === true) {
            $classList[] = 'mega-menu-links';
        }

        if ($depth === 0 && $this->treeHasMegaItems($tree)) {
            $classList[] = 'has-mega-menu';
        }

        $attrs = [
            'class' => implode(' ', array_unique($classList)),
            'data-depth' => (string) $depth,
        ];

        if (($options['is_mega_panel'] ?? false) === true) {
            $attrs['data-mega-panel'] = '1';
        }

        if (($options['mega_columns'] ?? null) !== null) {
            $attrs['style'] = '--cms-mega-columns:' . (int) $options['mega_columns'];
        }

        $html = '<ul' . $this->attributesToHtml($attrs) . '>';

        foreach ($tree as $node) {
            $html .= $this->renderNode($node, $ctx, $depth, $options);
        }

        $html .= '</ul>';

        return $html;
    }

    protected function renderNode(array $node, array $ctx, int $depth, array $options = []): string
    {
        /** @var MenuItem|null $item */
        $item = $node['item'] ?? null;
        if (!$item instanceof MenuItem) {
            return '';
        }

        $item = $this->hooks->applyFilters('cms.menu.item', $item, $ctx);
        if (!($item instanceof MenuItem)) {
            return '';
        }

        $data = $this->itemData($item);

        if (!$this->passesVisibility($data['visibility'] ?? null, $ctx)) {
            return '';
        }

        $children = is_array($node['children'] ?? null) ? $node['children'] : [];
        $hasChildren = !empty($children);
        $megaEnabled = $hasChildren && $this->isMegaEnabled($data, $depth);
        $megaColumns = $this->resolveMegaColumns($data);
        $continuation = $this->isContinuationItem($data, $depth);
        $isInsideMegaColumn = ($options['inside_mega_column'] ?? false) === true;

        $classList = [
            'cms-menu__item',
            'cms-menu__item--depth-' . $depth,
            'menu-item',
        ];

        if ($hasChildren) {
            $classList[] = 'menu-item-has-children';
        }

        if ($megaEnabled) {
            $classList[] = 'mega-menu-item';
            $classList[] = 'mega-menu';
            $classList[] = 'mega';
            $classList[] = 'mega-' . $megaColumns;
        }

        if ($continuation) {
            $classList[] = 'cont-menu';
            $classList[] = 'mega-menu-continuation';
        }

        if ($isInsideMegaColumn && $depth >= 1) {
            $classList[] = 'mega-menu-group';
        }

        $customClasses = $this->extractCustomClasses($item, $data);
        $classList = array_merge($classList, $customClasses);

        $attrs = [
            'class' => implode(' ', array_unique(array_filter($classList))),
            'data-depth' => (string) $depth,
        ];

        $cssId = $this->extractCssId($item, $data);
        if ($cssId !== null) {
            $attrs['id'] = $cssId;
        }

        if ($hasChildren) {
            $attrs['data-has-children'] = '1';
        }

        if ($megaEnabled) {
            $attrs['data-mega'] = '1';
            $attrs['data-mega-columns'] = (string) $megaColumns;
        }

        if ($continuation) {
            $attrs['data-continuation'] = '1';
        }

        $html = '<li' . $this->attributesToHtml($attrs) . '>';
        $html .= $this->renderItem(
            $item,
            $data,
            $ctx,
            $hasChildren,
            $megaEnabled,
            $depth,
            $isInsideMegaColumn,
            $continuation
        );

        if ($hasChildren) {
            if ($megaEnabled) {
                $html .= $this->renderMegaChildren($children, $ctx, $megaColumns, $depth + 1);
            } else {
                $childOptions = [];

                if ($isInsideMegaColumn) {
                    $childOptions['inside_mega_column'] = true;
                }

                if ($continuation) {
                    $childOptions['is_continuation_list'] = true;
                }

                $html .= $this->renderTree($children, $ctx, $depth + 1, $childOptions);
            }
        }

        $html .= '</li>';

        return $html;
    }

    protected function renderItem(
        MenuItem $item,
        array $data,
        array $ctx,
        bool $hasChildren = false,
        bool $isMega = false,
        int $depth = 0,
        bool $isInsideMegaColumn = false,
        bool $isContinuation = false
    ): string {
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

        $target = (string) ($item->target ?? $data['target'] ?? '');
        $rel = trim((string) ($item->rel ?? $data['rel'] ?? ''));

        $linkClasses = ['cms-menu__link'];

        if ($hasChildren) {
            $linkClasses[] = 'cms-menu__link--parent';
        }

        if ($isMega) {
            $linkClasses[] = 'cms-menu__link--mega-trigger';
        }

        if ($isInsideMegaColumn && $depth >= 1 && $hasChildren) {
            $linkClasses[] = 'mega-menu__heading-link';
        }

        if ($isContinuation) {
            $linkClasses[] = 'cont-menu__link';
        }

        $targetAttr = $target ? ' target="' . e($target) . '"' : '';
        $relAttr = $rel !== '' ? ' rel="' . e($rel) . '"' : '';
        $titleText = (string) ($item->description ?: $label);
        $titleAttr = $titleText !== '' ? ' title="' . e($titleText) . '"' : '';
        $ariaAttr = $hasChildren ? ' aria-haspopup="true" aria-expanded="false"' : '';

        $indicator = '';
        if ($hasChildren) {
            $indicatorClass = $isMega || $depth === 0
                ? 'cms-menu__indicator cms-menu__indicator--mega'
                : 'cms-menu__indicator cms-menu__indicator--submenu';

            $indicator = '<span class="' . e($indicatorClass) . '" aria-hidden="true">' .
                $this->renderIndicatorSvg($isMega || $depth === 0 ? 'down' : 'right') .
                '</span>';
        }

        return '<a class="' . e(implode(' ', array_unique($linkClasses))) . '" href="' . e($url) . '"' . $targetAttr . $relAttr . $titleAttr . $ariaAttr . '><span class="cms-menu__label">' . e($label) . '</span>' . $indicator . '</a>';
    }

    protected function renderIndicatorSvg(string $direction = 'down'): string
    {
        if ($direction === 'right') {
            return <<<HTML
<svg class="cms-menu__indicator-svg" width="14" height="14" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
    <path d="M7 4L13 10L7 16" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
</svg>
HTML;
        }

        return <<<HTML
<svg class="cms-menu__indicator-svg" width="14" height="14" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
    <path d="M5 7L10 12L15 7" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
</svg>
HTML;
    }

    protected function renderMegaChildren(array $children, array $ctx, int $columns, int $depth): string
    {
        $columns = max(2, min(6, $columns));
        $grouped = array_fill(1, $columns, []);
        $autoBucket = 1;

        foreach ($children as $node) {
            $item = $node['item'] ?? null;
            $data = $item instanceof MenuItem ? $this->itemData($item) : [];
            $assigned = (int) data_get($data, 'mega_menu.column', 0);

            if ($assigned >= 1 && $assigned <= $columns) {
                $grouped[$assigned][] = $node;
                continue;
            }

            $grouped[$autoBucket][] = $node;
            $autoBucket = $autoBucket >= $columns ? 1 : $autoBucket + 1;
        }

        $html = '';

        $panelAttrs = [
            'class' => implode(' ', [
                'cms-menu',
                'cms-menu--depth-' . $depth,
                'mega-menu-panel',
                'has-mega-menu',
            ]),
            'data-depth' => (string) $depth,
            'data-mega-panel' => '1',
            'style' => '--cms-mega-columns:' . $columns,
        ];

        $html .= '<ul' . $this->attributesToHtml($panelAttrs) . '>';

        foreach ($grouped as $index => $nodes) {
            if ($nodes === []) {
                continue;
            }

            $columnAttrs = [
                'class' => implode(' ', [
                    'cms-menu__item',
                    'mega-menu-column',
                    'mega-menu-column--' . $index,
                ]),
                'data-mega-column' => '1',
                'data-column-index' => (string) $index,
            ];

            $html .= '<li' . $this->attributesToHtml($columnAttrs) . '>';
            $html .= '<ul class="cms-menu cms-menu--depth-' . ($depth + 1) . ' mega-menu-links" data-depth="' . ($depth + 1) . '" data-mega-column-list="1">';

            foreach ($nodes as $node) {
                $html .= $this->renderNode($node, $ctx, $depth + 1, [
                    'inside_mega_column' => true,
                ]);
            }

            $html .= '</ul>';
            $html .= '</li>';
        }

        $html .= '</ul>';

        return $html;
    }

    protected function itemData(MenuItem $item): array
    {
        return is_array($item->data) ? $item->data : [];
    }

    protected function treeHasMegaItems(array $tree): bool
    {
        foreach ($tree as $node) {
            $item = $node['item'] ?? null;
            if (!$item instanceof MenuItem) {
                continue;
            }

            $data = $this->itemData($item);
            $children = is_array($node['children'] ?? null) ? $node['children'] : [];

            if (!empty($children) && $this->isMegaEnabled($data, 0)) {
                return true;
            }
        }

        return false;
    }

    protected function isMegaEnabled(array $data, int $depth): bool
    {
        return $depth === 0 && (bool) data_get($data, 'mega_menu.enabled', false);
    }

    protected function isContinuationItem(array $data, int $depth): bool
    {
        return $depth >= 1 && (bool) data_get($data, 'mega_menu.continuation', false);
    }

    protected function resolveMegaColumns(array $data): int
    {
        return max(2, min(6, (int) data_get($data, 'mega_menu.columns', 4) ?: 4));
    }

    protected function extractCustomClasses(MenuItem $item, array $data): array
    {
        $raw = [];

        if (isset($item->css_class) && is_string($item->css_class) && trim($item->css_class) !== '') {
            $raw[] = $item->css_class;
        }

        if (isset($data['css_class']) && is_string($data['css_class']) && trim($data['css_class']) !== '') {
            $raw[] = $data['css_class'];
        }

        if (isset($data['custom_class']) && is_string($data['custom_class']) && trim($data['custom_class']) !== '') {
            $raw[] = $data['custom_class'];
        }

        if (isset($data['menu_item_cclass']) && is_string($data['menu_item_cclass']) && trim($data['menu_item_cclass']) !== '') {
            $raw[] = $data['menu_item_cclass'];
        }

        $classes = [];

        foreach ($raw as $value) {
            foreach (preg_split('/\s+/', trim($value)) ?: [] as $token) {
                $token = trim($token);
                if ($token === '') {
                    continue;
                }

                $token = preg_replace('/[^A-Za-z0-9\-\_\:]/', '', $token);
                if ($token !== '') {
                    $classes[] = $token;
                }
            }
        }

        return array_values(array_unique($classes));
    }

    protected function extractCssId(MenuItem $item, array $data): ?string
    {
        $raw = null;

        if (isset($item->css_id) && is_string($item->css_id) && trim($item->css_id) !== '') {
            $raw = $item->css_id;
        } elseif (isset($data['css_id']) && is_string($data['css_id']) && trim($data['css_id']) !== '') {
            $raw = $data['css_id'];
        }

        if ($raw === null) {
            return null;
        }

        $id = trim($raw);
        $id = preg_replace('/[^A-Za-z0-9\-\_\:]/', '-', $id) ?? '';
        $id = trim($id, '-');

        if ($id === '') {
            return null;
        }

        if (!preg_match('/^[A-Za-z]/', $id)) {
            $id = 'menu-item-' . $id;
        }

        return $id;
    }

    protected function attributesToHtml(array $attrs): string
    {
        $parts = [];

        foreach ($attrs as $key => $value) {
            if ($value === null || $value === '') {
                continue;
            }

            $parts[] = $key . '="' . e((string) $value) . '"';
        }

        return $parts ? ' ' . implode(' ', $parts) : '';
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

        if (preg_match('#^(mailto:|tel:|javascript:)#i', $url)) {
            return $url;
        }

        $parsed = parse_url($url);

        if ($parsed === false) {
            $parsed = ['path' => $url];
        }

        $scheme = $parsed['scheme'] ?? null;
        $host = $parsed['host'] ?? null;

        $path = $parsed['path'] ?? '';
        if ($path === '' && !isset($scheme) && !isset($host)) {
            $path = $url;
        }

        $path = '/' . ltrim((string) $path, '/');

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

        $isRelative = !isset($scheme) && !isset($host);

        $isSameHost = false;
        if (isset($host)) {
            $appHost = parse_url((string) url('/'), PHP_URL_HOST);

            if (is_string($appHost) && $appHost !== '' && strcasecmp($host, $appHost) === 0) {
                $isSameHost = true;
            }
        }

        if ($isRelative || $isSameHost) {
            $lastSeg = basename($path);

            if (!str_contains($lastSeg, '.')) {
                $path = rtrim($path, '/') . '/';
            }
        }

        if ($isRelative) {
            $out = $path;
        } else {
            $out = '';
            $out .= $scheme ? ($scheme . '://') : '';

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

    protected function passesVisibility(mixed $visibility, array $ctx): bool
    {
        if ($visibility === null) {
            return true;
        }

        if (is_array($visibility)) {
            $enabled = $visibility['enabled'] ?? null;
            if ($enabled === false) {
                return true;
            }

            $auth = $visibility['auth'] ?? 'all';
            $user = $ctx['user'] ?? auth()->user();

            return match ($auth) {
                'guest' => $user === null,
                'auth' => $user !== null,
                default => true,
            };
        }

        return true;
    }
}