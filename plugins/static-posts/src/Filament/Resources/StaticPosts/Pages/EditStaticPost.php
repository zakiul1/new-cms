<?php

namespace Plugins\StaticPosts\Filament\Resources\StaticPosts\Pages;

use Filament\Actions\Action;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Enums\Width;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Plugins\StaticPosts\Filament\Resources\StaticPosts\StaticPostResource;

class EditStaticPost extends EditRecord
{
    protected static string $resource = StaticPostResource::class;

    /** @var int[] */
    protected array $featuredMediaIds = [];

    /** @var int[] */
    protected array $productMediaIds = [];

    public function getMaxContentWidth(): Width
    {
        return Width::Full;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('cancel')
                ->label('Cancel')
                ->url(static::getResource()::getUrl('index'))
                ->color('gray'),

            Action::make('view')
                ->label('View')
                ->icon('heroicon-o-eye')
                ->visible(fn(): bool => filled($this->record?->slug))
                ->url(fn() => url('/static/' . $this->record->slug))
                ->openUrlInNewTab(),

            Action::make('save')
                ->label('Update')
                ->color('primary')
                ->action(function (): void {
                    $this->save();
                }),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        if (!$this->record) {
            $data['featured_media_ids'] = [];
            $data['product_media_ids'] = [];
            return $data;
        }

        $postId = (int) $this->record->getKey();

        $data['featured_media_ids'] = DB::table('post_media')
            ->where('post_id', $postId)
            ->where('role', 'featured')
            ->orderBy('sort_order')
            ->pluck('media_id')
            ->map(fn($v) => (int) $v)
            ->values()
            ->all();

        $data['product_media_ids'] = DB::table('post_media')
            ->where('post_id', $postId)
            ->where('role', 'product')
            ->orderBy('sort_order')
            ->pluck('media_id')
            ->map(fn($v) => (int) $v)
            ->values()
            ->all();

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $this->featuredMediaIds = is_array($data['featured_media_ids'] ?? null)
            ? array_values(array_filter(array_map(fn($v) => (int) $v, $data['featured_media_ids'])))
            : [];
        unset($data['featured_media_ids']);

        $this->productMediaIds = is_array($data['product_media_ids'] ?? null)
            ? array_values(array_filter(array_map(fn($v) => (int) $v, $data['product_media_ids'])))
            : [];
        unset($data['product_media_ids']);

        // keep core featured_media_id in sync with first featured image
        $data['featured_media_id'] = $this->featuredMediaIds[0] ?? null;

        return $data;
    }

    protected function afterSave(): void
    {
        $this->syncMediaRole('featured', $this->featuredMediaIds);
        $this->syncMediaRole('product', $this->productMediaIds);

        Cache::forget('cms:sitemap:xml:v2');

        // ✅ No custom Notification here, Filament already shows "Saved"
    }

    /**
     * @param int[] $idsIn
     */
    private function syncMediaRole(string $role, array $idsIn): void
    {
        if (!$this->record) {
            return;
        }

        $postId = (int) $this->record->getKey();

        // sanitize + unique
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

        DB::table('post_media')
            ->where('post_id', $postId)
            ->where('role', $role)
            ->delete();

        if ($ids === []) {
            return;
        }

        $now = now();
        $rows = [];
        foreach ($ids as $i => $mediaId) {
            $rows[] = [
                'post_id' => $postId,
                'media_id' => $mediaId,
                'role' => $role,
                'sort_order' => $i,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        DB::table('post_media')->insert($rows);
    }
}