<?php

namespace Plugins\BlogPosts\Filament\Resources\BlogPosts\Pages;

use App\Cms\Content\Slugger;
use App\Models\PostMedia;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Plugins\BlogPosts\Filament\Resources\BlogPosts\BlogPostResource;

class CreateBlogPost extends CreateRecord
{
    protected static string $resource = BlogPostResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Force type
        $data['type'] = 'blog_post';

        // Ensure a unique slug
        $slugger = app(Slugger::class);

        $title = (string) ($data['title'] ?? '');
        $slug = (string) ($data['slug'] ?? '');

        $data['slug'] = $slugger->uniqueSlug(
            $slug !== '' ? $slug : $title,
            \App\Models\Post::class,
            'slug'
        );

        // Normalize meta JSON if provided as string
        if (isset($data['meta_json']) && is_string($data['meta_json'])) {
            $decoded = json_decode($data['meta_json'], true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $data['meta_json'] = $decoded;
            }
        }

        return $data;
    }

    protected function handleRecordCreation(array $data): Model
    {
        // Extract category IDs from form (if set)
        $categoryIds = Arr::pull($data, 'blog_category_ids', []);

        // Extract media IDs from form
        $featuredMediaIds = Arr::pull($data, 'featured_media_ids', []);
        $productMediaIds = Arr::pull($data, 'product_media_ids', []);

        /** @var Model $record */
        $record = static::getModel()::create($data);

        // Sync categories (taxonomy: blog_category)
        if (is_array($categoryIds)) {
            $record->terms()->syncWithoutDetaching($categoryIds);
        }

        // Sync featured + product media roles
        $this->syncMediaRoles($record, $featuredMediaIds, $productMediaIds);

        // Keep featured_media_id aligned to first featured
        if (!empty($featuredMediaIds)) {
            $record->featured_media_id = $featuredMediaIds[0] ?? null;
            $record->save();
        }

        // Clear sitemap cache (same behavior as Static Posts)
        Cache::forget('cms:sitemap:xml:v2');

        return $record;
    }

    protected function syncMediaRoles(Model $record, array $featuredMediaIds, array $productMediaIds): void
    {
        // Remove existing media links for this post
        PostMedia::query()->where('post_id', $record->getKey())->delete();

        $sort = 0;

        foreach ($featuredMediaIds as $mediaId) {
            PostMedia::create([
                'post_id' => $record->getKey(),
                'media_id' => $mediaId,
                'role' => 'featured',
                'sort_order' => $sort++,
            ]);
        }

        $sort = 0;

        foreach ($productMediaIds as $mediaId) {
            PostMedia::create([
                'post_id' => $record->getKey(),
                'media_id' => $mediaId,
                'role' => 'product',
                'sort_order' => $sort++,
            ]);
        }
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}