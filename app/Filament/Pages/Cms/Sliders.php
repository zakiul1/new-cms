<?php

namespace App\Filament\Pages\Cms;

use App\Cms\Plugins\PluginManager;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Enums\Width;
use UnitEnum;

class Sliders extends Page
{
    protected static string|UnitEnum|null $navigationGroup = 'Appearance';
    protected static ?string $navigationLabel = 'Sliders';
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-rectangle-stack';
    protected static ?int $navigationSort = 25;

    protected string $view = 'filament.pages.cms.sliders';

    public static function shouldRegisterNavigation(): bool
    {
        $plugins = app(PluginManager::class);
        return in_array('slider', $plugins->enabledSlugs(), true);
       
    }

    public function getMaxContentWidth(): Width
    {
        return Width::Full;
    }
}
