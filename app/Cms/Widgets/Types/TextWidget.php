<?php

namespace App\Cms\Widgets\Types;

use App\Cms\Widgets\Contracts\WidgetType;
use Filament\Forms\Components\RichEditor;

class TextWidget implements WidgetType
{
    public static function key(): string
    {
        return 'text';
    }

    public static function name(): string
    {
        return 'Text / HTML';
    }

    public static function schema(): array
    {
        return [
            RichEditor::make('content')
                ->label('Content')
                ->toolbarButtons([
                    'bold',
                    'italic',
                    'underline',
                    'strike',
                    'link',
                    'blockquote',
                    'bulletList',
                    'orderedList',
                    'h2',
                    'h3',
                    'undo',
                    'redo',
                ])
                ->columnSpanFull(),
        ];
    }

    public static function render(array $settings, array $ctx = []): string
    {
        return (string) ($settings['content'] ?? '');
    }
}