<?php

namespace Plugins\BlogPosts\Filament\Resources\BlogPosts\Pages;

use App\Models\Post;
use Filament\Actions\Action;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Plugins\BlogPosts\Filament\Resources\BlogPosts\BlogPostResource;

class CreateBlogPost extends CreateRecord
{
    protected static string $resource = BlogPostResource::class;

    /**
     * Make a globally-unique slug in `posts.slug`.
     * (Your CMS requires uniqueness across posts + pages, etc.)
     */
    protected function makeUniqueSlug(string $titleOrSlug): string
    {
        $base = Str::slug($titleOrSlug);
        $base = $base !== '' ? $base : 'blog-post';

        $slug = $base;
        $i = 2;

        while (Post::query()->where('slug', $slug)->exists()) {
            $slug = $base . '-' . $i;
            $i++;
        }

        return $slug;
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Force type
        $data['type'] = 'blog_post';

        // ✅ FIX: posts.author_id is required (DB has no default)
        $data['author_id'] = (int) auth()->id();

        // Ensure a unique slug (slug field is hidden on create UI, so we auto-generate)
        $title = (string) ($data['title'] ?? '');
        $givenSlug = (string) ($data['slug'] ?? '');
        $data['slug'] = $this->makeUniqueSlug($givenSlug !== '' ? $givenSlug : $title);

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
        /**
         * ✅ Category IDs
         * Your form uses: blog_category_term_ids
         * Some older code used: blog_category_ids
         * So support both.
         */
        $categoryIds = Arr::pull($data, 'blog_category_term_ids', []);
        if (empty($categoryIds)) {
            $categoryIds = Arr::pull($data, 'blog_category_ids', []);
        }

        // Extract media IDs from form
        $featuredMediaIds = Arr::pull($data, 'featured_media_ids', []);
        $productMediaIds = Arr::pull($data, 'product_media_ids', []);

        /** @var Model $record */
        $record = static::getModel()::create($data);

        // Sync categories (taxonomy: blog_category)
        if (is_array($categoryIds)) {
            $ids = collect($categoryIds)
                ->filter(fn($v) => is_numeric($v))
                ->map(fn($v) => (int) $v)
                ->unique()
                ->values()
                ->all();

            if (!empty($ids)) {
                $record->terms()->syncWithoutDetaching($ids);
            }
        }

        // Sync featured + product media roles into `post_media` table
        $this->syncMediaRoles($record, (array) $featuredMediaIds, (array) $productMediaIds);

        // Keep featured_media_id aligned to first featured
        $firstFeatured = is_array($featuredMediaIds) ? ($featuredMediaIds[0] ?? null) : null;
        if ($firstFeatured !== null) {
            $record->featured_media_id = (int) $firstFeatured;
            $record->save();
        }

        // Clear sitemap cache (same behavior as Static Posts)
        Cache::forget('cms:sitemap:xml:v2');

        return $record;
    }

    /**
     * ✅ Your project does NOT have App\Models\PostMedia class.
     * So we write directly to the pivot table: post_media
     */
    protected function syncMediaRoles(Model $record, array $featuredMediaIds, array $productMediaIds): void
    {
        $postId = (int) $record->getKey();

        // Remove existing media links for this post
        DB::table('post_media')->where('post_id', $postId)->delete();

        // featured
        $sort = 0;
        foreach ($featuredMediaIds as $mediaId) {
            if (!is_numeric($mediaId)) {
                continue;
            }

            DB::table('post_media')->insert([
                'post_id' => $postId,
                'media_id' => (int) $mediaId,
                'role' => 'featured',
                'sort_order' => $sort++,
            ]);
        }

        // product
        $sort = 0;
        foreach ($productMediaIds as $mediaId) {
            if (!is_numeric($mediaId)) {
                continue;
            }

            DB::table('post_media')->insert([
                'post_id' => $postId,
                'media_id' => (int) $mediaId,
                'role' => 'product',
                'sort_order' => $sort++,
            ]);
        }
    }

    /**
     * Header buttons: Back + Create + Create & add another
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('back')
                ->label('Back')
                ->color('gray')
                ->url(fn() => $this->getResource()::getUrl('index')),



            Action::make('create_another')
                ->label('Create & add another')
                ->color('gray')
                ->action('createAnother'),
            Action::make('create')
                ->label('Create')
                ->color('primary')
                ->action('create'),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}