<?php

namespace App\Cms\Widgets\Types;

use App\Cms\Widgets\Contracts\WidgetType;
use App\Models\Taxonomy;
use App\Models\Term;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;

class CategoriesWidget implements WidgetType
{
    public static function key(): string
    {
        return 'categories';
    }

    public static function name(): string
    {
        return 'Categories';
    }

    public static function schema(): array
    {
        return [
            TextInput::make('limit')
                ->label('Max categories')
                ->numeric()
                ->default(10)
                ->minValue(1)
                ->maxValue(100),

            Toggle::make('show_count')
                ->label('Show post count')
                ->default(false),
        ];
    }

    public static function render(array $settings, array $ctx = []): string
    {
        $limit = (int) ($settings['limit'] ?? 10);
        $limit = max(1, min(100, $limit));

        $taxonomyId = Taxonomy::query()->where('key', 'category')->value('id');
        if (!$taxonomyId) {
            return '';
        }

        $terms = Term::query()
            ->where('taxonomy_id', $taxonomyId)
            ->where('visibility', 'public')
            ->orderBy('name')
            ->limit($limit)
            ->get();

        if ($terms->isEmpty()) {
            return '';
        }

        $showCount = (bool) ($settings['show_count'] ?? false);

        $base = (string) app(\App\Cms\Core\SettingsRepository::class)->get('core', 'category_base', 'category');
        $base = trim($base, '/');

        $html = '<ul class="cms-categories">';
        foreach ($terms as $t) {
            $url = function_exists('cms_term_url')
                ? cms_term_url($t)
                : (function_exists('cms_slug_url')
                    ? cms_slug_url($base . '/' . $t->slug)
                    : url('/' . trim($base, '/') . '/' . trim((string) $t->slug, '/') . '/'));

            $label = e((string) $t->name);
            if ($showCount && isset($t->posts_count)) {
                $label .= ' (' . (int) $t->posts_count . ')';
            }

            $html .= '<li><a href="' . e($url) . '">' . $label . '</a></li>';
        }
        $html .= '</ul>';

        return $html;
    }
}