<?php

namespace Plugins\BlogPosts\Filament\Resources\BlogPosts\Pages;

use App\Cms\Content\Slugger;
use App\Models\PostMedia;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Plugins\BlogPosts\Filament\Resources\BlogPosts\BlogPostResource;

class EditBlogPost extends EditRecord
{
    protected static string $resource = BlogPostResource::class;

    /**
     * Hide the default "Edit {record}" heading like you wanted.
     */
    public function getHeading(): string
    {
        return '';
    }

    public function getSubheading(): ?string
    {
        return null;
    }

    /**
     * Remove breadcrumbs (also removes "Edit {title}" breadcrumb line).
     */
    public function getBreadcrumbs(): array
    {
        return [];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Force type
        $data['type'] = 'blog_post';

        // Ensure slug remains unique
        $slugger = app(Slugger::class);

        $title = (string) ($data['title'] ?? '');
        $slug = (string) ($data['slug'] ?? '');

        $data['slug'] = $slugger->uniqueSlug(
            $slug !== '' ? $slug : $title,
            \App\Models\Post::class,
            'slug',
            $this->record->getKey()
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

    protected function afterSave(): void
    {
        $data = $this->form->getState();

        // Extract category IDs from form
        $categoryIds = Arr::get($data, 'blog_category_ids', []);

        // Extract media IDs from form
        $featuredMediaIds = Arr::get($data, 'featured_media_ids', []);
        $productMediaIds = Arr::get($data, 'product_media_ids', []);

        // Sync categories (taxonomy: blog_category)
        if (is_array($categoryIds)) {
            $taxonomy = \App\Models\Taxonomy::query()->where('key', 'blog_category')->first();
            if ($taxonomy) {
                $existing = $this->record->terms()
                    ->whereHas('taxonomy', fn($q) => $q->whereKey($taxonomy->getKey()))
                    ->pluck('terms.id')
                    ->all();

                if (!empty($existing)) {
                    $this->record->terms()->detach($existing);
                }
            }

            $this->record->terms()->syncWithoutDetaching($categoryIds);
        }

        // Sync featured + product media roles
        $this->syncMediaRoles($this->record, (array) $featuredMediaIds, (array) $productMediaIds);

        // Keep featured_media_id aligned to first featured
        if (!empty($featuredMediaIds)) {
            $this->record->featured_media_id = $featuredMediaIds[0] ?? null;
            $this->record->save();
        }

        // Clear sitemap cache
        Cache::forget('cms:sitemap:xml:v2');
    }

    protected function syncMediaRoles($record, array $featuredMediaIds, array $productMediaIds): void
    {
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

    /**
     * ✅ Header buttons:
     * - Cancel (go back) on the left
     * - Update (save) on the right
     * - Delete (danger) on the right
     */
    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()
                ->label('Delete')
                ->color('danger'),
            Actions\Action::make('cancel')
                ->label('Cancel')
                ->color('gray')
                ->url($this->getResource()::getUrl('index'))
                ->icon('heroicon-o-x-mark'),

            Actions\Action::make('update')
                ->label('Update')
                ->color('primary')
                ->action('save')
                ->icon('heroicon-o-check'),


        ];
    }
}