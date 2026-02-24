<?php

namespace Plugins\StaticPosts\Filament\Resources\StaticCategories\Pages;

use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Plugins\StaticPosts\Filament\Resources\StaticCategories\StaticCategoryResource;

class ListStaticCategories extends ListRecords
{
    protected static string $resource = StaticCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Add Static Category'),
        ];
    }
}