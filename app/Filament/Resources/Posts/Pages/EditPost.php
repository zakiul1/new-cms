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

    /** @var int[] */
    protected array $productMediaIds = [];

    public function getMaxContentWidth(): Width
    {
        return Width::Full;
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        // SAFEST load (works even if pluck('media.id') behaves weird)
        $data['product_media_ids'] = $this->record
            ->productMedia()
            ->orderBy('post_media.sort_order')
            ->get()
            ->pluck('id')
            ->map(fn($id) => (int) $id)
            ->all();

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $this->productMediaIds = is_array($data['product_media_ids'] ?? null)
            ? array_values(array_filter(array_map(fn($v) => (int) $v, $data['product_media_ids'])))
            : [];

        unset($data['product_media_ids']);

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

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}