<?php

namespace Plugins\StaticPosts\Filament\Resources\StaticCategories\Pages;

use Filament\Actions\Action;
use Filament\Resources\Pages\CreateRecord;
use Plugins\StaticPosts\Filament\Resources\StaticCategories\StaticCategoryResource;

class CreateStaticCategory extends CreateRecord
{
    protected static string $resource = StaticCategoryResource::class;

    protected bool $createAnother = false;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('back')
                ->label('Back')
                ->color('gray')
                ->url(static::getResource()::getUrl('index')),

            Action::make('createAnother')
                ->label('Create & create another')
                ->action(function (): void {
                    $this->createAnother = true;
                    $this->create();
                }),

            Action::make('create')
                ->label('Create')
                ->color('primary')
                ->action(function (): void {
                    $this->createAnother = false;
                    $this->create();
                }),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->createAnother
            ? static::getResource()::getUrl('create')
            : static::getResource()::getUrl('index');
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Force taxonomy_id = static_category
        $taxonomyId = \App\Models\Taxonomy::where('key', 'static_category')->value('id');
        $data['taxonomy_id'] = $taxonomyId;

        return $data;
    }
}