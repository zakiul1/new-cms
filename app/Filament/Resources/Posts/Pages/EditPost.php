<?php

namespace App\Filament\Resources\Posts\Pages;

use App\Cms\Content\PermalinkManager;
use App\Cms\Content\Slugger;
use App\Filament\Resources\Posts\PostResource;
use App\Models\Redirect;
use App\Models\SlugHistory;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Enums\Width;
use Illuminate\Support\Facades\Cache;

class EditPost extends EditRecord
{
    protected static string $resource = PostResource::class;

    /** @var int[] */
    protected array $featuredMediaIds = [];

    // ✅ For WP-like redirect/history
    protected string $oldSlug = '';
    protected string $oldCanonicalPath = '';

    public function getMaxContentWidth(): Width
    {
        return Width::Full;
    }

    public function getBreadcrumbs(): array
    {
        return [];
    }

    public function getTitle(): string
    {
        return 'Edit Post ';
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        // ✅ Fill Featured Images (multiple) from pivot (role=featured)
        $data['featured_media_ids'] = $this->record
            ->mediaPivot()
            ->wherePivot('role', 'featured')
            ->orderBy('post_media.sort_order')
            ->get()
            ->pluck('id')
            ->map(fn($id) => (int) $id)
            ->all();

        // ✅ Fallback: if no pivot featured exists yet, use legacy single column
        if (empty($data['featured_media_ids']) && !empty($data['featured_media_id'])) {
            $data['featured_media_ids'] = [(int) $data['featured_media_id']];
        }

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // ✅ capture old permalink BEFORE anything changes
        $this->oldSlug = (string) ($this->record->slug ?? '');
        $this->oldCanonicalPath = $this->canonicalPathFor($this->record);

        // ✅ capture Featured Images (multiple)
        $this->featuredMediaIds = is_array($data['featured_media_ids'] ?? null)
            ? array_values(array_filter(array_map(fn($v) => (int) $v, $data['featured_media_ids'])))
            : [];

        unset($data['featured_media_ids']);

        // ✅ keep legacy column in sync (first featured image)
        $data['featured_media_id'] = $this->featuredMediaIds[0] ?? null;

        $data['type'] = 'post';

        /** @var Slugger $slugger */
        $slugger = app(Slugger::class);

        // ✅ GLOBAL uniqueness across post + page
        $data['slug'] = empty($data['slug'])
            ? $slugger->uniqueGlobalSlugFromTitle((string) ($data['title'] ?? ''), (int) $this->record->id)
            : $slugger->uniqueGlobalSlugFromSlug((string) $data['slug'], (int) $this->record->id);

        return $data;
    }

    protected function afterSave(): void
    {
        $this->syncFeaturedMedia();

        // ✅ refresh record (so categories/relations saved by Filament are visible)
        $this->record->refresh();

        $newSlug = (string) ($this->record->slug ?? '');
        $newCanonicalPath = $this->canonicalPathFor($this->record);

        // 1) ✅ slug history (WP old slug redirect support)
        if ($this->oldSlug !== '' && $newSlug !== '' && $this->oldSlug !== $newSlug) {
            SlugHistory::query()->create([
                'entity_type' => 'post',
                'entity_id' => (int) $this->record->id,
                'old_slug' => $this->oldSlug,
            ]);
        }

        // 2) ✅ canonical redirect (covers: slug change OR permalink structure change)
        if ($this->oldCanonicalPath !== '' && $newCanonicalPath !== '' && $this->oldCanonicalPath !== $newCanonicalPath) {
            Redirect::query()->updateOrCreate(
                ['from_path' => $this->oldCanonicalPath],
                ['to_path' => $newCanonicalPath, 'status_code' => 301]
            );

            // Optional: keep legacy /blog/{old-slug} redirects working
            if ($this->oldSlug !== '' && $this->oldSlug !== $newSlug) {
                Redirect::query()->updateOrCreate(
                    ['from_path' => '/blog/' . ltrim($this->oldSlug, '/')],
                    ['to_path' => $newCanonicalPath, 'status_code' => 301]
                );
            }
        }

        // ✅ clear sitemap cache because URLs may change
        Cache::forget('cms:sitemap:xml:v2');
    }

    private function canonicalPathFor($post): string
    {
        if (!$post) {
            return '/';
        }

        // ✅ Always use PermalinkManager (matches your CMS settings)
        /** @var PermalinkManager $permalinks */
        $permalinks = app(PermalinkManager::class);

        $path = $permalinks->postPath($post);

        // If "plain" mode, postPath returns '/?p=ID' (not a path). We store a path-only redirect.
        // In that case, fall back to the resolved path using slug format (best possible path).
        if (str_contains($path, '?p=')) {
            $slug = trim((string) ($post->slug ?? ''), '/');
            return $slug === '' ? '/' : '/' . $slug;
        }

        return $path;
    }

    private function syncFeaturedMedia(): void
    {
        if (!$this->record) {
            return;
        }

        // ✅ unique, keep order
        $seen = [];
        $ids = [];

        foreach ($this->featuredMediaIds as $id) {
            if ($id <= 0 || isset($seen[$id])) {
                continue;
            }
            $seen[$id] = true;
            $ids[] = $id;
        }

        // Prefer helper if it exists on Post model
        if (method_exists($this->record, 'syncMediaRole')) {
            $this->record->syncMediaRole('featured', $ids);
            return;
        }

        // Manual fallback
        if ($ids === []) {
            $this->record->mediaPivot()->wherePivot('role', 'featured')->detach();
            return;
        }

        // Clear only featured role items, then attach ordered
        $this->record->mediaPivot()->wherePivot('role', 'featured')->detach();

        $sync = [];
        foreach ($ids as $i => $id) {
            $sync[$id] = [
                'role' => 'featured',
                'sort_order' => $i,
            ];
        }

        $this->record->mediaPivot()->attach($sync);
    }

    protected function getHeaderActions(): array
    {
        return [
            // ✅ LEFT SIDE: Add Post
            Action::make('addPost')
                ->label('Add Post')
                ->icon('heroicon-o-plus')
                ->color('primary')
                ->url($this->getResource()::getUrl('create'))
                // 👇 pushes everything else to the right
                ->extraAttributes(['class' => 'me-auto']),

            // ✅ RIGHT SIDE: existing buttons
            DeleteAction::make(),

            Action::make('view')
                ->label('View')
                ->color('gray')
                ->icon('heroicon-o-arrow-top-right-on-square')
                ->url(function (): string {
                    /** @var PermalinkManager $permalinks */
                    $permalinks = app(PermalinkManager::class);

                    return $permalinks->postUrl($this->record);
                }, true),

            Action::make('back')
                ->label('Back')
                ->color('gray')
                ->icon('heroicon-o-arrow-left')
                ->url($this->getResource()::getUrl('index')),

            // ✅ Save (recommended Filament way)
            $this->getSaveFormAction()
                ->label('Save changes')
                ->icon('heroicon-o-check')
                ->color('info')
                ->keyBindings(['mod+s'])
                ->formId('form'),
        ];
    }
}