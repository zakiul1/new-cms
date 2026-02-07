<?php

namespace App\Filament\Resources\MediaCategoryResource\Pages;

use App\Filament\Resources\MediaCategoryResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Filament\Actions\Action;
use App\Filament\Resources\MediaResource;

class EditMediaCategory extends EditRecord
{
    protected static string $resource = MediaCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('uploadMedia')
                ->label('Upload Media')
                ->icon('heroicon-o-arrow-up-tray')
                ->color('primary')
                ->url(MediaResource::getUrl('create')),
            DeleteAction::make(),
            // ✅ Save in header (important: formId)


            // optional actions:
            $this->getCancelFormAction(),
            $this->getSaveFormAction()
                ->label('Save changes')
                ->formId('form'),

        ];
    }
}