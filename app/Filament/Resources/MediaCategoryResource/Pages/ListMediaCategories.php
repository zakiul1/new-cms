<?php

namespace App\Filament\Resources\MediaCategoryResource\Pages;

use App\Filament\Resources\MediaCategoryResource;
use App\Filament\Resources\MediaResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListMediaCategories extends ListRecords
{
    protected static string $resource = MediaCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('uploadMedia')
                ->label('Upload Media')
                ->icon('heroicon-o-arrow-up-tray')
                ->color('primary')
                ->url(MediaResource::getUrl('create')),

            Actions\CreateAction::make()
                ->label('Add Category'),
        ];
    }
}