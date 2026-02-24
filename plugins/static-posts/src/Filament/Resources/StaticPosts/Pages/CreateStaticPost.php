<?php

namespace Plugins\StaticPosts\Filament\Resources\StaticPosts\Pages;

use App\Cms\Content\Slugger;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Filament\Support\Enums\Width;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Plugins\StaticPosts\Filament\Resources\StaticPosts\StaticPostResource;

class CreateStaticPost extends CreateRecord
{
    protected static string $resource = StaticPostResource::class;

    /** @var int[] */
    protected array $featuredMediaIds = [];

    /** @var int[] */
    protected array $productMediaIds = [];

    protected bool $createAnother = false;

    public function getMaxContentWidth(): Width
    {
        return Width::Full;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('cancel')
                ->label('Cancel')
                ->color('gray')
                ->url(static::getResource()::getUrl('index')),

            Action::make('createAnother')
                ->label('Create & create another')
                ->action(function (): void {
                    $this->createAnother = true;

                    // ✅ Call Filament's real create action (Livewire)
                    $this->create();
                }),

            Action::make('create')
                ->label('Create')
                ->color('primary')
                ->action(function (): void {
                    $this->createAnother = false;

                    // ✅ Call Filament's real create action (Livewire)
                    $this->create();
                }),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->createAnother
            ? static::getResource()::getUrl('create')
            : static::getResource()::getUrl('index');
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $this->featuredMediaIds = is_array($data['featured_media_ids'] ?? null)
            ? array_values(array_filter(array_map(fn($v) => (int) $v, $data['featured_media_ids'])))
            : [];
        unset($data['featured_media_ids']);

        $this->productMediaIds = is_array($data['product_media_ids'] ?? null)
            ? array_values(array_filter(array_map(fn($v) => (int) $v, $data['product_media_ids'])))
            : [];
        unset($data['product_media_ids']);

        // Legacy sync
        $data['featured_media_id'] = $this->featuredMediaIds[0] ?? null;

        $data['type'] = 'static_post';

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
        $this->syncMediaRole('featured', $this->featuredMediaIds);
        $this->syncMediaRole('product', $this->productMediaIds);

        Cache::forget('cms:sitemap:xml:v2');

        Notification::make()
            ->title('Static Post created')
            ->success()
            ->send();
    }

    /**
     * Sync media to post_media pivot using correct FK: post_id (NOT static_post_id).
     *
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