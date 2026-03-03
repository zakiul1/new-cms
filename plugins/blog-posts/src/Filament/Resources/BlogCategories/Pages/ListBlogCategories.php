<?php

namespace Plugins\BlogPosts\Filament\Resources\BlogCategories\Pages;

use Filament\Resources\Pages\ListRecords;
use Plugins\BlogPosts\Filament\Resources\BlogCategories\BlogCategoryResource;

class ListBlogCategories extends ListRecords
{
    protected static string $resource = BlogCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            \Filament\Actions\CreateAction::make(),
        ];
    }
}