<?php

namespace App\Filament\Resources\Posts\Pages;

use App\Cms\Content\Slugger;
use App\Filament\Resources\Posts\PostResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditPost extends EditRecord
{
    protected static string $resource = PostResource::class;

    public function getMaxContentWidth(): ?string
    {
        return 'full';
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['type'] = 'post';

        /** @var Slugger $slugger */
        $slugger = app(Slugger::class);

        $data['slug'] = empty($data['slug'])
            ? $slugger->uniquePostSlug('post', (string) ($data['title'] ?? ''), $this->record->id)
            : $slugger->uniqueFromSlug('post', (string) $data['slug'], $this->record->id);

        return $data;
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}