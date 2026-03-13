<?php

namespace App\Cms\Widgets;

use App\Cms\Core\CmsCacheVersions;
use App\Cms\Hooks\Hooks;
use App\Models\WidgetPlacement;
use Illuminate\Support\Facades\Cache;

class SidebarRenderer
{
    public function __construct(
        protected Hooks $hooks,
        protected CmsCacheVersions $versions,
        protected WidgetRegistry $registry,
    ) {
    }

    public function render(string $areaKey, array $ctx = []): string
    {
        $ver = $this->versions->get('widget_area', $areaKey);

        // global render version (theme/plugins/customizer publish)
        $renderVer = $this->versions->getRender();

        $cacheKey = "cms:sidebar:area:{$areaKey}:v{$ver}:r{$renderVer}";

        return (string) Cache::remember($cacheKey, now()->addMinutes(30), function () use ($areaKey, $ctx) {
            $placements = WidgetPlacement::query()
                ->with('widget')
                ->where('widget_area_key', $areaKey)
                ->orderBy('sort_order')
                ->get();

            $html = '';

            foreach ($placements as $placement) {
                $widget = $placement->widget;
                if (!$widget || !$widget->is_enabled) {
                    continue;
                }

                if (!$this->passesVisibility(is_array($placement->visibility) ? $placement->visibility : null, $ctx)) {
                    continue;
                }

                $typeClass = $this->registry->get($widget->type);
                if (!$typeClass) {
                    continue;
                }

                $settings = is_array($widget->settings) ? $widget->settings : [];
                $over = is_array($placement->overrides) ? $placement->overrides : [];

                // Merge overrides into settings for widget type rendering
                $finalSettings = array_replace_recursive($settings, $over);

                $widgetHtml = (string) $typeClass::render($finalSettings, $ctx);
                $widgetHtml = $this->hooks->applyFilters('cms.sidebar.widget_html', $widgetHtml, $widget, $ctx);

                // Premium overrides that affect wrapper/title (WP-like)
                $titleOverride = $over['title'] ?? null;
                $hideTitle = (bool) ($over['hide_title'] ?? false);
                $cssClass = (string) ($over['css_class'] ?? '');
                $wrapperTag = (string) ($over['wrapper_tag'] ?? 'section');
                $titleTag = (string) ($over['title_tag'] ?? 'div');

                if (!in_array($wrapperTag, ['div', 'aside', 'section'], true)) {
                    $wrapperTag = 'section';
                }

                // Use non-heading tags by default to avoid heading-order problems
                if (!in_array($titleTag, ['div', 'p', 'span', 'h2', 'h3', 'h4'], true)) {
                    $titleTag = 'div';
                }

                $title = ($titleOverride !== null && trim((string) $titleOverride) !== '')
                    ? (string) $titleOverride
                    : (string) ($widget->title ?? '');

                $wrapClass = 'cms-widget cms-widget--' . e($widget->type);
                if (trim($cssClass) !== '') {
                    $wrapClass .= ' ' . e($cssClass);
                }

                $html .= '<' . $wrapperTag . ' class="' . $wrapClass . '">';

                if (!$hideTitle && trim($title) !== '') {
                    $html .= '<' . $titleTag . ' class="cms-widget__title">' . e($title) . '</' . $titleTag . '>';
                }

                $html .= $widgetHtml;
                $html .= '</' . $wrapperTag . '>';
            }

            $html = $this->hooks->applyFilters('cms.sidebar.html', $html, $areaKey, $ctx);

            return $html;
        });
    }

    protected function passesVisibility(?array $rules, array $ctx): bool
    {
        if (empty($rules)) {
            return true;
        }

        $auth = $rules['auth'] ?? 'any';

        if ($auth === 'guest') {
            return auth()->guest();
        }

        // accept both naming conventions
        if ($auth === 'auth' || $auth === 'logged_in') {
            return auth()->check();
        }

        return true;
    }
}