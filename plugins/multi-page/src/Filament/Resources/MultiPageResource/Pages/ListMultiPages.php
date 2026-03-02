<?php

namespace Plugins\MultiPage\Filament\Resources\MultiPageResource\Pages;

use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Plugins\MultiPage\Filament\Resources\MultiPageResource;

class ListMultiPages extends ListRecords
{
    protected static string $resource = MultiPageResource::class;

    /**
     * ✅ Filament v5: ensure Create action exists in header.
     */
    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Add Multipages'),
        ];
    }
}