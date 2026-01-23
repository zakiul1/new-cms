<?php

namespace App\Filament\Resources\Pages\Pages;

use App\Cms\Content\Slugger;
use App\Filament\Resources\Pages\PageResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditPage extends EditRecord
{
    protected static string $resource = PageResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['type'] = 'page';

        /** @var Slugger $slugger */
        $slugger = app(Slugger::class);

        // Slug optional:
        // - if empty => generate from title
        // - if given => sanitize + auto-rename if duplicate
        $data['slug'] = empty($data['slug'])
            ? $slugger->uniquePostSlug('page', (string) ($data['title'] ?? ''), $this->record->id)
            : $slugger->uniqueFromSlug('page', (string) $data['slug'], $this->record->id);

        return $data;
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}