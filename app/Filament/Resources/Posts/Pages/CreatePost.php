<?php

namespace App\Filament\Resources\Posts\Pages;

use App\Cms\Content\Slugger;
use App\Filament\Resources\Posts\PostResource;
use Filament\Resources\Pages\CreateRecord;
use Filament\Support\Enums\Width;

class CreatePost extends CreateRecord
{
    protected static string $resource = PostResource::class;

    /**
     * Holds repeater rows from the form:
     * [
     *   ['media_id' => 12],
     *   ['media_id' => 25],
     * ]
     *
     * @var array<int, array<string, mixed>>
     */
    protected array $productGalleryItems = [];

    public function getMaxContentWidth(): Width
    {
        return Width::Full;
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // ✅ Grab virtual repeater field (NOT in posts table)
        $this->productGalleryItems = is_array($data['product_gallery_items'] ?? null)
            ? $data['product_gallery_items']
            : [];

        unset($data['product_gallery_items']);

        // core post defaults
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
        if (! $this->record) {
            return;
        }

        // Extract IDs in the exact order of repeater rows
        $ids = [];
        foreach ($this->productGalleryItems as $row) {
            $id = (int) ($row['media_id'] ?? 0);
            if ($id > 0) {
                $ids[] = $id;
            }
        }

        // Remove duplicates but keep first occurrence order
        $seen = [];
        $orderedUnique = [];
        foreach ($ids as $id) {
            if (isset($seen[$id])) {
                continue;
            }
            $seen[$id] = true;
            $orderedUnique[] = $id;
        }

        // ✅ Remove ONLY product-role items if empty
        if ($orderedUnique === []) {
            // productMedia() relationship should already be scoped to role=product
            $this->record->productMedia()->detach();
            return;
        }

        // Build sync payload with ordering
        $sync = [];
        foreach ($orderedUnique as $i => $id) {
            $sync[$id] = [
                'role' => 'product',
                'sort_order' => $i,
            ];
        }

        /**
         * IMPORTANT:
         * - productMedia() should be a relationship scoped to pivot role=product
         * - so sync() will only manage that subset and won't touch other pivot roles.
         */
        $this->record->productMedia()->sync($sync);
    }
}
