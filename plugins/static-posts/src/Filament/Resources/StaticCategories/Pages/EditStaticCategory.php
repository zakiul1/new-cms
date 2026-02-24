<?php

namespace Plugins\StaticPosts\Filament\Resources\StaticCategories\Pages;

use Filament\Actions\Action;
use Filament\Resources\Pages\EditRecord;
use Plugins\StaticPosts\Filament\Resources\StaticCategories\StaticCategoryResource;

class EditStaticCategory extends EditRecord
{
    protected static string $resource = StaticCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('back')
                ->label('Back')
                ->color('gray')
                ->url(static::getResource()::getUrl('index')),

            Action::make('save')
                ->label('Update')
                ->color('primary')
                ->action(function (): void {
                    $this->save();
                }),
        ];
    }
}