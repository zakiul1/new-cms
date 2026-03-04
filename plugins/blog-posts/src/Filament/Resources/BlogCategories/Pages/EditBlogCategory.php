<?php

namespace Plugins\BlogPosts\Filament\Resources\BlogCategories\Pages;

use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Plugins\BlogPosts\Filament\Resources\BlogCategories\BlogCategoryResource;

class EditBlogCategory extends EditRecord
{
    protected static string $resource = BlogCategoryResource::class;
    public function getHeading(): string
    {
        return '';
    }

    public function getSubheading(): ?string
    {
        return null;
    }
    public function getBreadcrumbs(): array
    {
        return [];
    }
    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}