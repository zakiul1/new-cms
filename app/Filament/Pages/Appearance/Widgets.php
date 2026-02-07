<?php

namespace App\Filament\Pages\Appearance;

use BackedEnum;
use Filament\Pages\Page;
use UnitEnum;

class Widgets extends Page
{
    protected static string|UnitEnum|null $navigationGroup = 'Appearance';
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-squares-2x2';
    protected static ?int $navigationSort = 30;

    protected string $view = 'filament.pages.appearance.widgets';
}