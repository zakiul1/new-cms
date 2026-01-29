<?php

namespace App\Filament\Resources\Posts\Pages;

use App\Cms\Content\Slugger;
use App\Filament\Resources\Posts\PostResource;
use Filament\Resources\Pages\CreateRecord;
use Filament\Support\Enums\Width;

class CreatePost extends CreateRecord
{
    protected static string $resource = PostResource::class;

    /** @var int[] */
    protected array $productMediaIds = [];
    protected function getHeaderActions(): array
    {
        return [
            $this->getCancelFormAction(),
            // Create
            $this->getCreateAnotherFormAction(),
            $this->getCreateFormAction(),      // Create & create another (optional)
            // Cancel (optional)
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
        $this->productMediaIds = is_array($data['product_media_ids'] ?? null)
            ? array_values(array_filter(array_map(fn($v) => (int) $v, $data['product_media_ids'])))
            : [];

        unset($data['product_media_ids']);

        $data['type'] = 'post';

        if (empty($data['author_id']) && auth()->check()) {
            $data['author_id'] = auth()->id();
        }

        $data['status'] = $data['status'] ?? 'draft';

        /** @var Slugger $slugger */
        $slugger = app(Slugger::class);

        $data['slug'] = empty($data['slug'])
            ? $slugger->uniquePostSlug('post', (string) ($data['title'] ?? ''))
            : $slugger->uniqueFromSlug('post', (string) $data['slug']);

        return $data;
    }

    protected function afterCreate(): void
    {
        $this->syncProductGallery();
    }

    private function syncProductGallery(): void
    {
        if (!$this->record) {
            return;
        }

        // unique, keep order
        $seen = [];
        $ids = [];
        foreach ($this->productMediaIds as $id) {
            if ($id <= 0 || isset($seen[$id])) {
                continue;
            }
            $seen[$id] = true;
            $ids[] = $id;
        }

        if ($ids === []) {
            $this->record->productMedia()->detach();
            return;
        }

        $sync = [];
        foreach ($ids as $i => $id) {
            $sync[$id] = [
                'role' => 'product',
                'sort_order' => $i,
            ];
        }

        $this->record->productMedia()->sync($sync);
    }
}