<?php

namespace Plugins\MultiPage\Filament\Resources\MultiPageResource\Pages;

use Filament\Resources\Pages\CreateRecord;
use Plugins\MultiPage\Filament\Resources\MultiPageResource;

class CreateMultiPage extends CreateRecord
{
    protected static string $resource = MultiPageResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['type'] = 'page';

        $data['meta_json'] = is_array($data['meta_json'] ?? null) ? $data['meta_json'] : [];
        $data['meta_json']['multipage'] = is_array($data['meta_json']['multipage'] ?? null)
            ? $data['meta_json']['multipage']
            : [];

        // ✅ default enable multipage for this resource
        $data['meta_json']['multipage']['enabled'] = true;

        return $data;
    }
}