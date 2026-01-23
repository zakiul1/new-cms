<?php

namespace App\Filament\Resources\Pages\Pages;

use App\Cms\Content\Slugger;
use App\Filament\Resources\Pages\PageResource;
use Filament\Resources\Pages\CreateRecord;

class CreatePage extends CreateRecord
{
    protected static string $resource = PageResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['type'] = 'page';

        if (empty($data['author_id']) && auth()->check()) {
            $data['author_id'] = auth()->id();
        }

        $data['status'] = $data['status'] ?? 'draft';

        /** @var Slugger $slugger */
        $slugger = app(Slugger::class);

        // Slug optional:
        // - if empty => generate from title
        // - if given => sanitize + auto-rename if duplicate
        $data['slug'] = empty($data['slug'])
            ? $slugger->uniquePostSlug('page', (string) ($data['title'] ?? ''))
            : $slugger->uniqueFromSlug('page', (string) $data['slug']);

        return $data;
    }
}