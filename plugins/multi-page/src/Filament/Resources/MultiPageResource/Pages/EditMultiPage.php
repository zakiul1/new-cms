<?php

namespace Plugins\MultiPage\Filament\Resources\MultiPageResource\Pages;

use Filament\Resources\Pages\EditRecord;
use Plugins\MultiPage\Filament\Resources\MultiPageResource;

class EditMultiPage extends EditRecord
{
    protected static string $resource = MultiPageResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['type'] = 'page';

        $data['meta_json'] = is_array($data['meta_json'] ?? null) ? $data['meta_json'] : [];
        $data['meta_json']['multipage']['enabled'] = (bool) ($data['meta_json']['multipage']['enabled'] ?? true);

        return $data;
    }
}