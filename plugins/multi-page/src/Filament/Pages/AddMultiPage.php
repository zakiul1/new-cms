<?php

namespace Plugins\MultiPage\Filament\Pages;

use BackedEnum;
use Filament\Pages\Page;
use Plugins\MultiPage\Filament\Resources\MultiPageResource;
use UnitEnum;

class AddMultiPage extends Page
{
    protected static ?string $navigationLabel = 'Add Multipages';
    protected static string|UnitEnum|null $navigationGroup = 'Pages';
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-plus-circle';
    protected static ?int $navigationSort = 51;

    // View is required by Filament Page, but we never render it because we redirect.
    protected string $view = 'multi-page::filament.pages.blank';

    public function mount(): void
    {
        $this->redirect(MultiPageResource::getUrl('create'));
    }
}