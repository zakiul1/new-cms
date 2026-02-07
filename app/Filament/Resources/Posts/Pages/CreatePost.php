<?php

namespace App\Filament\Resources\Posts\Pages;

use App\Cms\Content\Slugger;
use App\Filament\Resources\Posts\PostResource;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Filament\Support\Enums\Width;
use Illuminate\Support\Facades\Cache;

class CreatePost extends CreateRecord
{
    protected static string $resource = PostResource::class;

    /** @var int[] */
    protected array $featuredMediaIds = [];

    protected function getHeaderActions(): array
    {
        return [
            $this->getCancelFormAction(),
            $this->getCreateAnotherFormAction()->formId('form'),
            $this->getCreateFormAction()->formId('form'),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return static::$resource::getUrl('index');
    }

    public function getMaxContentWidth(): Width
    {
        return Width::Full;
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // ✅ Featured Images (multiple)
        $this->featuredMediaIds = is_array($data['featured_media_ids'] ?? null)
            ? array_values(array_filter(array_map(fn($v) => (int) $v, $data['featured_media_ids'])))
            : [];

        unset($data['featured_media_ids']);

        // ✅ Keep legacy single column in sync (first image)
        $data['featured_media_id'] = $this->featuredMediaIds[0] ?? null;

        $data['type'] = 'post';

        if (empty($data['author_id']) && auth()->check()) {
            $data['author_id'] = auth()->id();
        }

        $data['status'] = $data['status'] ?? 'draft';

        /** @var Slugger $slugger */
        $slugger = app(Slugger::class);

        // ✅ GLOBAL slug namespace (posts + pages)
        $data['slug'] = empty($data['slug'])
            ? $slugger->uniqueGlobalSlugFromTitle((string) ($data['title'] ?? ''), ignorePostId: null)
            : $slugger->uniqueGlobalSlugFromSlug((string) $data['slug'], ignorePostId: null);

        return $data;
    }

    protected function afterCreate(): void
    {
        $this->syncFeaturedMedia();

        // ✅ clear sitemap cache (new post/page affects sitemap)
        Cache::forget('cms:sitemap:xml:v2');

        Notification::make()
            ->title('Post created')
            ->body('You can now add categories and publish it when ready.')
            ->success()
            ->send();
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

        // ✅ If you already have helper: $this->record->syncMediaRole('featured', $ids);
        // Use it if available, otherwise do manual sync below.

        if (method_exists($this->record, 'syncMediaRole')) {
            $this->record->syncMediaRole('featured', $ids);
            return;
        }

        // Manual sync (fallback)
        if ($ids === []) {
            $this->record->mediaPivot()->wherePivot('role', 'featured')->detach();
            return;
        }

        $sync = [];
        foreach ($ids as $i => $id) {
            $sync[$id] = [
                'role' => 'featured',
                'sort_order' => $i,
            ];
        }

        // Sync only featured role items
        $this->record->mediaPivot()->wherePivot('role', 'featured')->detach();
        $this->record->mediaPivot()->attach($sync);
    }
}