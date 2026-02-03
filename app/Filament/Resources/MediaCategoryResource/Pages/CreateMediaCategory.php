<?php

namespace App\Filament\Resources\MediaCategoryResource\Pages;

use App\Filament\Resources\MediaCategoryResource;
use App\Models\Taxonomy;
use Filament\Resources\Pages\CreateRecord;

class CreateMediaCategory extends CreateRecord
{
    protected static string $resource = MediaCategoryResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Force taxonomy_id for safety
        $taxonomyId = (int) Taxonomy::firstOrCreate(
            ['key' => 'media_category'],
            ['label' => 'Media Categories', 'hierarchical' => true],
        )->id;

        $data['taxonomy_id'] = $taxonomyId;

        return $data;
    }
}