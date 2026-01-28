<?php

namespace App\Filament\Resources\MenuItemResource\Pages;

use App\Filament\Resources\MenuItemResource;
use Filament\Resources\Pages\CreateRecord;

class CreateMenuItem extends CreateRecord
{
    protected static string $resource = MenuItemResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Keep menu context when coming from Menu -> Items button
        if (blank($data['menu_id'])) {
            $menuId = request()->integer('menu');
            if ($menuId > 0) {
                $data['menu_id'] = $menuId;
            }
        }

        return $data;
    }
}