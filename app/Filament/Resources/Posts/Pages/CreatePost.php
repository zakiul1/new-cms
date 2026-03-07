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

    protected array $featuredMediaIds = [];

    protected array $productMediaIds = [];

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

        if (empty($data['author_id']) && auth()->check()) {
            $data['author_id'] = auth()->id();
        }

        $data['status'] = $data['status'] ?? 'draft';

        /** @var Slugger $slugger */
        $slugger = app(Slugger::class);

        $data['slug'] = empty($data['slug'])
            ? $slugger->uniqueGlobalSlugFromTitle((string) ($data['title'] ?? ''), ignorePostId: null)
            : $slugger->uniqueGlobalSlugFromSlug((string) $data['slug'], ignorePostId: null);

        return $data;
    }

    protected function afterCreate(): void
    {
        $this->syncFeaturedMedia();
        $this->syncProductMedia();

        Cache::forget('cms:sitemap:xml:v2');

        Notification::make()
            ->title('Post created')
            ->body('You can now continue editing the post.')
            ->success()
            ->send();
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

        $sync = [];

        foreach ($ids as $index => $id) {
            $sync[$id] = [
                'role' => $role,
                'sort_order' => $index,
            ];
        }

        $this->record->mediaPivot()->wherePivot('role', $role)->detach();
        $this->record->mediaPivot()->attach($sync);
    }
}