<?php

namespace Plugins\SiatexTags\Filament\Resources\SiatexTagResource\Pages;

use Filament\Resources\Pages\CreateRecord;
use Plugins\SiatexTags\Filament\Resources\SiatexTagResource;

class CreateSiatexTag extends CreateRecord
{
    protected static string $resource = SiatexTagResource::class;

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('edit', ['record' => $this->record]);
    }
}