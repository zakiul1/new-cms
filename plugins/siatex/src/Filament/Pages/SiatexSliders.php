<?php

namespace Plugins\siatex\Filament\Pages;

use Filament\Pages\Page;

class SiatexSliders extends Page
{
    protected static ?string $navigationLabel = 'Siatex Sliders';

    // Filament v5 strict types
    protected static string|\UnitEnum|null $navigationGroup = 'Appearance';
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-rectangle-group';
    protected static ?int $navigationSort = 45;

    protected string $view = 'plugins.siatex::filament.pages.siatex-sliders';
}