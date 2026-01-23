<?php

namespace App\Filament\Resources\Posts\Pages;

use App\Cms\Content\Slugger;
use App\Filament\Resources\Posts\PostResource;
use Filament\Resources\Pages\CreateRecord;
use Filament\Support\Enums\Width;

class CreatePost extends CreateRecord
{
    protected static string $resource = PostResource::class;

    public function getMaxContentWidth(): Width
    {
        return Width::Full;
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['type'] = 'post';

        if (empty($data['author_id']) && auth()->check()) {
            $data['author_id'] = auth()->id();
        }

        $data['status'] = $data['status'] ?? 'draft';

        /** @var Slugger $slugger */
        $slugger = app(Slugger::class);

        $data['slug'] = empty($data['slug'])
            ? $slugger->uniquePostSlug('post', (string) ($data['title'] ?? ''))
            : $slugger->uniqueFromSlug('post', (string) $data['slug']);

        return $data;
    }
}