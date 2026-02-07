<?php

namespace App\Cms\Widgets\Contracts;

use Filament\Forms\Components\Component;

interface WidgetType
{
    public static function key(): string;
    public static function name(): string;

    /** @return array<Component> */
    public static function schema(): array;

    /** @param array<string,mixed> $settings */
    public static function render(array $settings, array $ctx = []): string;
}