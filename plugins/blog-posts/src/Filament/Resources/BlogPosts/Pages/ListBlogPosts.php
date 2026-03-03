<?php

namespace Plugins\BlogPosts\Filament\Resources\BlogPosts\Pages;

use Filament\Resources\Pages\ListRecords;
use Plugins\BlogPosts\Filament\Resources\BlogPosts\BlogPostResource;

class ListBlogPosts extends ListRecords
{
    protected static string $resource = BlogPostResource::class;

    protected function getHeaderActions(): array
    {
        return [
            \Filament\Actions\CreateAction::make(),
        ];
    }
}