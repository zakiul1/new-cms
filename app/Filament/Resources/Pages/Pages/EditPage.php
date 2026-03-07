<?php

namespace App\Filament\Resources\Pages\Pages;

use App\Cms\Content\PermalinkManager;
use App\Cms\Content\Slugger;
use App\Filament\Resources\Pages\PageResource;
use App\Models\Redirect;
use App\Models\SlugHistory;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Cache;

class EditPage extends EditRecord
{
    protected static string $resource = PageResource::class;

    /** @var int[] */
    protected array $featuredMediaIds = [];

    /** @var int[] */
    protected array $productMediaIds = [];

    protected string $oldSlug = '';
    protected string $oldCanonicalPath = '';

    protected function mutateFormDataBeforeFill(array $data): array
    {
        if ($this->record && method_exists($this->record, 'mediaPivot')) {
            $data['featured_media_ids'] = $this->record
                ->mediaPivot()
                ->wherePivot('role', 'featured')
                ->orderBy('post_media.sort_order')
                ->get()
                ->pluck('id')
                ->map(fn($id) => (int) $id)
                ->all();

            $data['product_media_ids'] = $this->record
                ->mediaPivot()
                ->wherePivot('role', 'product')
                ->orderBy('post_media.sort_order')
                ->get()
                ->pluck('id')
                ->map(fn($id) => (int) $id)
                ->all();
        } else {
            $data['featured_media_ids'] = [];
            $data['product_media_ids'] = [];
        }

        if (empty($data['featured_media_ids']) && !empty($data['featured_media_id'])) {
            $data['featured_media_ids'] = [(int) $data['featured_media_id']];
        }

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $this->oldSlug = (string) ($this->record->slug ?? '');
        $this->oldCanonicalPath = $this->canonicalPathFor($this->record);

        $this->featuredMediaIds = is_array($data['featured_media_ids'] ?? null)
            ? array_values(array_filter(array_map(fn($v) => (int) $v, $data['featured_media_ids'])))
            : [];

        unset($data['featured_media_ids']);

        $this->productMediaIds = is_array($data['product_media_ids'] ?? null)
            ? array_values(array_filter(array_map(fn($v) => (int) $v, $data['product_media_ids'])))
            : [];

        unset($data['product_media_ids']);

        $data['featured_media_id'] = $this->featuredMediaIds[0] ?? null;

        $data['type'] = 'page';

        /** @var Slugger $slugger */
        $slugger = app(Slugger::class);

        $data['slug'] = empty($data['slug'])
            ? $slugger->uniqueGlobalSlugFromTitle((string) ($data['title'] ?? ''), (int) $this->record->id)
            : $slugger->uniqueGlobalSlugFromSlug((string) $data['slug'], (int) $this->record->id);

        $data['meta_json'] = is_array($data['meta_json'] ?? null) ? $data['meta_json'] : [];
        $data['content_json'] = is_array($data['content_json'] ?? null) ? $data['content_json'] : [];

        $data['meta_json'] = array_replace_recursive([
            'template' => null,
            'parent_id' => null,
            'slider' => [
                'title' => null,
            ],
            'product' => '',
            'seo' => [
                'title' => null,
                'description' => null,
            ],
            'sub_description' => '',
        ], $data['meta_json']);

        $data['content_json'] = array_replace_recursive([
            'html' => $data['content_json']['html'] ?? '',
        ], $data['content_json']);

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
                'entity_type' => 'page',
                'entity_id' => (int) $this->record->id,
                'old_slug' => $this->oldSlug,
            ]);
        }

        if ($this->oldCanonicalPath !== '' && $newCanonicalPath !== '' && $this->oldCanonicalPath !== $newCanonicalPath) {
            Redirect::query()->updateOrCreate(
                ['from_path' => $this->oldCanonicalPath],
                ['to_path' => $newCanonicalPath, 'status_code' => 301]
            );
        }

        Cache::forget('cms:sitemap:xml:v2');
    }

    private function canonicalPathFor($page): string
    {
        if (!$page) {
            return '/';
        }

        /** @var PermalinkManager $permalinks */
        $permalinks = app(PermalinkManager::class);

        return $permalinks->pagePath($page);
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

    /**
     * Sync media IDs into post_media pivot for a given role, keeping order.
     *
     * @param string $role
     * @param int[] $idsIn
     */
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

        if (!method_exists($this->record, 'mediaPivot')) {
            return;
        }

        if ($ids === []) {
            $this->record->mediaPivot()->wherePivot('role', $role)->detach();
            return;
        }

        $this->record->mediaPivot()->wherePivot('role', $role)->detach();

        $sync = [];
        foreach ($ids as $i => $id) {
            $sync[$id] = [
                'role' => $role,
                'sort_order' => $i,
            ];
        }

        $this->record->mediaPivot()->attach($sync);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('addPage')
                ->label('Add Page')
                ->icon('heroicon-o-plus')
                ->color('primary')
                ->url($this->getResource()::getUrl('create')),

            DeleteAction::make(),

            Action::make('view')
                ->label('View')
                ->color('gray')
                ->icon('heroicon-o-arrow-top-right-on-square')
                ->url(function (): string {
                    /** @var PermalinkManager $permalinks */
                    $permalinks = app(PermalinkManager::class);

                    return $permalinks->pageUrl($this->record);
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