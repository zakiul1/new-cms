<?php

namespace App\Filament\Pages\Appearance;

use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Enums\Width;
use UnitEnum;

class Menus extends Page
{
    // force slug -> route becomes: filament.admin.pages.menus
    protected static ?string $slug = 'menus-builder';

    protected static string|UnitEnum|null $navigationGroup = 'Appearance';
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-bars-3';
    protected static ?string $navigationLabel = 'Menus Builder';
    protected static ?string $title = 'Menus Builder';

    // Filament v5: NON-static view property
    protected string $view = 'filament.pages.appearance.menus';

    public function getMaxContentWidth(): Width
    {
        return Width::Full;
    }
}