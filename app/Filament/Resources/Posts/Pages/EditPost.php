<?php

namespace App\Filament\Resources\Posts\Pages;

use App\Cms\Content\Slugger;
use App\Filament\Resources\Posts\PostResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Enums\Width;

class EditPost extends EditRecord
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

    protected function mutateFormDataBeforeFill(array $data): array
    {
        // ✅ Load existing product images into repeater rows in correct pivot sort order
        $ids = $this->record
            ->productMedia()
            ->orderBy('post_media.sort_order') // ensure correct order
            ->pluck('media.id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $data['product_gallery_items'] = array_map(
            fn (int $id) => ['media_id' => $id],
            $ids
        );

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // ✅ Grab virtual repeater field (NOT in posts table)
        $this->productGalleryItems = is_array($data['product_gallery_items'] ?? null)
            ? $data['product_gallery_items']
            : [];

        unset($data['product_gallery_items']);

        $data['type'] = 'post';

        /** @var Slugger $slugger */
        $slugger = app(Slugger::class);

        $data['slug'] = empty($data['slug'])
            ? $slugger->uniquePostSlug('post', (string) ($data['title'] ?? ''), (int) $this->record->id)
            : $slugger->uniqueFromSlug('post', (string) $data['slug'], (int) $this->record->id);

        return $data;
    }

    protected function afterSave(): void
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

        // ✅ If none selected, remove ONLY product links (productMedia() is scoped)
        if ($orderedUnique === []) {
            $this->record->productMedia()->detach();
            return;
        }

        // Build sync payload with ordering + role
        $sync = [];
        foreach ($orderedUnique as $i => $id) {
            $sync[$id] = [
                'role' => 'product',
                'sort_order' => $i,
            ];
        }

        /**
         * IMPORTANT:
         * productMedia() MUST be scoped to role=product, otherwise sync() may affect other roles.
         */
        $this->record->productMedia()->sync($sync);
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
