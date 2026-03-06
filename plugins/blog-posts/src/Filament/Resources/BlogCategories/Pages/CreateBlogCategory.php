<?php

namespace Plugins\BlogPosts\Filament\Resources\BlogCategories\Pages;

use App\Models\Taxonomy;
use Filament\Resources\Pages\CreateRecord;
use Plugins\BlogPosts\Filament\Resources\BlogCategories\BlogCategoryResource;

class CreateBlogCategory extends CreateRecord
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
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    public function getBreadcrumbs(): array
    {
        return [];
    }

    protected function getHeaderActions(): array
    {
        return [
            $this->getCancelFormAction(),
            $this->getCreateAnotherFormAction()->formId('form'),
            $this->getCreateFormAction()->formId('form'),
        ];
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $taxonomy = Taxonomy::query()->where('key', 'blog_category')->firstOrFail();

        $data['taxonomy_id'] = $taxonomy->getKey();
        $data['visibility'] = 'public';

        return $data;
    }
}