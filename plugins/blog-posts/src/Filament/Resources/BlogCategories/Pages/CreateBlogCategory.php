<?php

namespace Plugins\BlogPosts\Filament\Resources\BlogCategories\Pages;

use App\Models\Taxonomy;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Plugins\BlogPosts\Filament\Resources\BlogCategories\BlogCategoryResource;

class CreateBlogCategory extends CreateRecord
{
    protected static string $resource = BlogCategoryResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Force taxonomy_id to blog_category taxonomy
        $taxonomy = Taxonomy::query()->where('key', 'blog_category')->firstOrFail();
        $data['taxonomy_id'] = $taxonomy->getKey();

        return $data;
    }

    protected function handleRecordCreation(array $data): Model
    {
        /** @var Model $record */
        $record = static::getModel()::create($data);

        return $record;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}