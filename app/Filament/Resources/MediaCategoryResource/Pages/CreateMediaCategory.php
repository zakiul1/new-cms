<?php

namespace App\Filament\Resources\MediaCategoryResource\Pages;

use App\Filament\Resources\MediaCategoryResource;
use App\Filament\Resources\MediaResource;
use App\Models\Taxonomy;
use Filament\Actions\Action;
use Filament\Resources\Pages\CreateRecord;

class CreateMediaCategory extends CreateRecord
{
    protected static string $resource = MediaCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // ✅ Upload media
            Action::make('uploadMedia')
                ->label('Upload Media')
                ->icon('heroicon-o-arrow-up-tray')
                ->color('primary')
                ->url(MediaResource::getUrl('create')),

            // ✅ Cancel
            $this->getCancelFormAction(),

            // ✅ Create (important: formId so it submits from header)
            $this->getCreateFormAction()
                ->label('Create')
                ->formId('form'),
        ];
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $taxonomyId = (int) Taxonomy::firstOrCreate(
            ['key' => 'media_category'],
            ['label' => 'Media Categories', 'hierarchical' => true],
        )->id;

        $data['taxonomy_id'] = $taxonomyId;

        return $data;
    }
}