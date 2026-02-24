<?php

namespace Plugins\StaticPosts\Filament\Resources\StaticPosts\Pages;

use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Plugins\StaticPosts\Filament\Resources\StaticPosts\StaticPostResource;

class ListStaticPosts extends ListRecords
{
    protected static string $resource = StaticPostResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Add Static Post'),
        ];
    }
}