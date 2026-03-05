<?php

namespace App\Cms\Widgets\Types;

use App\Cms\Widgets\Contracts\WidgetType;
use Filament\Forms\Components\Textarea;

class ShortcodeWidget implements WidgetType
{
    public static function key(): string
    {
        return 'shortcode';
    }

    public static function name(): string
    {
        return 'Shortcode';
    }

    public static function schema(): array
    {
        return [
            Textarea::make('code')
                ->label('Shortcode')
                ->helperText('Example: [popular_tags] or [contact_form id="1"]')
                ->rows(4)
                ->required(),
        ];
    }

    public static function render(array $settings, array $ctx = []): string
    {
        // IMPORTANT: the shortcode rendering happens via the filter we add in CmsServiceProvider:
        // cms.sidebar.widget_html -> CMS_THE_CONTENT
        return (string) ($settings['code'] ?? '');
    }
}