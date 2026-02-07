<?php

namespace App\Cms\Widgets\Types;

use App\Cms\Menus\MenuRenderer;
use App\Cms\Widgets\Contracts\WidgetType;
use App\Models\Menu;
use Filament\Forms\Components\Select;

class MenuWidget implements WidgetType
{
    public static function key(): string
    {
        return 'menu';
    }

    public static function name(): string
    {
        return 'Navigation Menu';
    }

    public static function schema(): array
    {
        return [
            Select::make('menu_id')
                ->label('Menu')
                ->options(fn() => Menu::query()->orderBy('name')->pluck('name', 'id')->all())
                ->searchable()
                ->required(),
        ];
    }

    public static function render(array $settings, array $ctx = []): string
    {
        $menuId = (int) ($settings['menu_id'] ?? 0);
        if ($menuId <= 0) {
            return '';
        }

        // Render by menu_id directly (not by location)
        // We'll provide a helper on renderer.
        /** @var MenuRenderer $renderer */
        $renderer = app(MenuRenderer::class);

        // Quick render using temporary location-style logic:
        $menu = \App\Models\Menu::query()->find($menuId);
        if (!$menu)
            return '';

        // Create ctx marker and render via manual tree
        $items = \App\Models\MenuItem::query()
            ->where('menu_id', $menu->id)
            ->where('is_enabled', true)
            ->orderBy('parent_id')
            ->orderBy('sort_order')
            ->get();

        // Reuse internal behavior by calling protected methods isn't possible,
        // so keep it simple: use cms_menu_location only for location menus in MVP.
        // For now, show a basic flat list:
        $html = '<ul class="cms-menu">';
        foreach ($items as $item) {
            $html .= '<li><a href="' . e((string) ($item->url ?? '#')) . '">' . e((string) ($item->label ?? '')) . '</a></li>';
        }
        $html .= '</ul>';

        return $html;
    }
}