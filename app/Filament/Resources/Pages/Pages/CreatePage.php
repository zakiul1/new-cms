<?php

namespace App\Filament\Resources\Pages\Pages;

use App\Cms\Content\Slugger;
use App\Filament\Resources\Pages\PageResource;
use Filament\Resources\Pages\CreateRecord;

class CreatePage extends CreateRecord
{
    protected static string $resource = PageResource::class;

    /** @var int[] */
    protected array $featuredMediaIds = [];

    /** @var int[] */
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

        $data['featured_media_id'] = $this->featuredMediaIds[0] ?? null;

        $data['type'] = 'page';

        if (empty($data['author_id']) && auth()->check()) {
            $data['author_id'] = auth()->id();
        }

        $data['status'] = $data['status'] ?? 'draft';

        /** @var Slugger $slugger */
        $slugger = app(Slugger::class);

        $data['slug'] = empty($data['slug'])
            ? $slugger->uniqueGlobalSlugFromTitle((string) ($data['title'] ?? ''), ignorePostId: null)
            : $slugger->uniqueGlobalSlugFromSlug((string) $data['slug'], ignorePostId: null);

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

    protected function afterCreate(): void
    {
        $this->syncFeaturedMedia();
        $this->syncProductMedia();
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

        if (method_exists($this->record, 'mediaPivot')) {
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
    }
}