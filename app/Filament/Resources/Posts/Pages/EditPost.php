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

    protected array $featuredMediaIds = [];

    protected array $productMediaIds = [];

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
        return 'Edit Post';
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['featured_media_ids'] = $this->record
            ->mediaPivot()
            ->wherePivot('role', 'featured')
            ->orderBy('post_media.sort_order')
            ->get()
            ->pluck('id')
            ->map(fn($id) => (int) $id)
            ->all();

        if (empty($data['featured_media_ids']) && !empty($data['featured_media_id'])) {
            $data['featured_media_ids'] = [(int) $data['featured_media_id']];
        }

        $data['product_media_ids'] = $this->record
            ->mediaPivot()
            ->wherePivot('role', 'product')
            ->orderBy('post_media.sort_order')
            ->get()
            ->pluck('id')
            ->map(fn($id) => (int) $id)
            ->all();

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $this->oldSlug = (string) ($this->record->slug ?? '');
        $this->oldCanonicalPath = $this->canonicalPathFor($this->record);

        $this->featuredMediaIds = is_array($data['featured_media_ids'] ?? null)
            ? array_values(array_filter(array_map(fn($value) => (int) $value, $data['featured_media_ids'])))
            : [];

        unset($data['featured_media_ids']);

        $this->productMediaIds = is_array($data['product_media_ids'] ?? null)
            ? array_values(array_filter(array_map(fn($value) => (int) $value, $data['product_media_ids'])))
            : [];

        unset($data['product_media_ids']);

        $data['featured_media_id'] = $this->featuredMediaIds[0] ?? null;
        $data['type'] = 'post';

        /** @var Slugger $slugger */
        $slugger = app(Slugger::class);

        $data['slug'] = empty($data['slug'])
            ? $slugger->uniqueGlobalSlugFromTitle((string) ($data['title'] ?? ''), (int) $this->record->id)
            : $slugger->uniqueGlobalSlugFromSlug((string) $data['slug'], (int) $this->record->id);

        return $data;
    }

    protected function afterSave(): void
    {
        $this->syncFeaturedMedia();
        $this->syncProductMedia();

        $this->record->refresh();

        $newSlug = (string) ($this->record->slug ?? '');
        $newCanonicalPath = $this->canonicalPathFor($this->record);

        if ($this->oldSlug !== '' && $newSlug !== '' && $this->oldSlug !== $newSlug) {
            SlugHistory::query()->create([
                'entity_type' => 'post',
                'entity_id' => (int) $this->record->id,
                'old_slug' => $this->oldSlug,
            ]);
        }

        if ($this->oldCanonicalPath !== '' && $newCanonicalPath !== '' && $this->oldCanonicalPath !== $newCanonicalPath) {
            Redirect::query()->updateOrCreate(
                ['from_path' => $this->oldCanonicalPath],
                ['to_path' => $newCanonicalPath, 'status_code' => 301]
            );

            if ($this->oldSlug !== '' && $this->oldSlug !== $newSlug) {
                Redirect::query()->updateOrCreate(
                    ['from_path' => '/blog/' . ltrim($this->oldSlug, '/')],
                    ['to_path' => $newCanonicalPath, 'status_code' => 301]
                );
            }
        }

        Cache::forget('cms:sitemap:xml:v2');
    }

    private function canonicalPathFor($post): string
    {
        if (!$post) {
            return '/';
        }

        /** @var PermalinkManager $permalinks */
        $permalinks = app(PermalinkManager::class);

        $path = $permalinks->postPath($post);

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

        $this->syncMediaRole('featured', $this->featuredMediaIds);
    }

    private function syncProductMedia(): void
    {
        if (!$this->record) {
            return;
        }

        $this->syncMediaRole('product', $this->productMediaIds);
    }

    private function syncMediaRole(string $role, array $idsIn): void
    {
        if (!$this->record) {
            return;
        }

        $seen = [];
        $ids = [];

        foreach ($idsIn as $id) {
            $id = (int) $id;

            if ($id <= 0 || isset($seen[$id])) {
                continue;
            }

            $seen[$id] = true;
            $ids[] = $id;
        }

        if (method_exists($this->record, 'syncMediaRole')) {
            $this->record->syncMediaRole($role, $ids);
            return;
        }

        if ($ids === []) {
            $this->record->mediaPivot()->wherePivot('role', $role)->detach();
            return;
        }

        $this->record->mediaPivot()->wherePivot('role', $role)->detach();

        $sync = [];

        foreach ($ids as $index => $id) {
            $sync[$id] = [
                'role' => $role,
                'sort_order' => $index,
            ];
        }

        $this->record->mediaPivot()->attach($sync);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('addPost')
                ->label('Add Post')
                ->icon('heroicon-o-plus')
                ->color('primary')
                ->url($this->getResource()::getUrl('create'))
                ->extraAttributes(['class' => 'me-auto']),

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

            $this->getSaveFormAction()
                ->label('Save changes')
                ->icon('heroicon-o-check')
                ->color('info')
                ->keyBindings(['mod+s'])
                ->formId('form'),
        ];
    }
}