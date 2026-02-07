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

    // ✅ For WP-like redirect/history
    protected string $oldSlug = '';
    protected string $oldCanonicalPath = '';

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // ✅ capture old permalink BEFORE anything changes
        $this->oldSlug = (string) ($this->record->slug ?? '');
        $this->oldCanonicalPath = $this->canonicalPathFor($this->record);

        $data['type'] = 'page';

        /** @var Slugger $slugger */
        $slugger = app(Slugger::class);

        // ✅ GLOBAL uniqueness across post + page
        $data['slug'] = empty($data['slug'])
            ? $slugger->uniqueGlobalSlugFromTitle((string) ($data['title'] ?? ''), (int) $this->record->id)
            : $slugger->uniqueGlobalSlugFromSlug((string) $data['slug'], (int) $this->record->id);

        $data['meta_json'] = is_array($data['meta_json'] ?? null) ? $data['meta_json'] : [];
        $data['content_json'] = is_array($data['content_json'] ?? null) ? $data['content_json'] : [];

        $data['meta_json'] = array_replace_recursive([
            'template' => 'default',
            'parent_id' => null,
            'menu_order' => 0,
            'seo' => [
                'title' => null,
                'description' => null,
            ],
        ], $data['meta_json']);

        $data['content_json'] = array_replace_recursive([
            'html' => $data['content_json']['html'] ?? '',
        ], $data['content_json']);

        return $data;
    }

    protected function afterSave(): void
    {
        // refresh record so we get latest slug/meta
        $this->record->refresh();

        $newSlug = (string) ($this->record->slug ?? '');
        $newCanonicalPath = $this->canonicalPathFor($this->record);

        // 1) ✅ slug history (WP old slug redirect support)
        if ($this->oldSlug !== '' && $newSlug !== '' && $this->oldSlug !== $newSlug) {
            SlugHistory::query()->create([
                'entity_type' => 'page',
                'entity_id' => (int) $this->record->id,
                'old_slug' => $this->oldSlug,
            ]);
        }

        // 2) ✅ canonical redirect (covers slug changes)
        if ($this->oldCanonicalPath !== '' && $newCanonicalPath !== '' && $this->oldCanonicalPath !== $newCanonicalPath) {
            Redirect::query()->updateOrCreate(
                ['from_path' => $this->oldCanonicalPath],
                ['to_path' => $newCanonicalPath, 'status_code' => 301]
            );
        }

        // ✅ clear sitemap cache because URLs may change
        Cache::forget('cms:sitemap:xml:v2');
    }

    private function canonicalPathFor($page): string
    {
        if (!$page) {
            return '/';
        }

        /** @var PermalinkManager $permalinks */
        $permalinks = app(PermalinkManager::class);

        // Pages are always /{slug}
        return $permalinks->pagePath($page);
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

            // ✅ Better: use Filament’s built-in save action for header
            $this->getSaveFormAction()
                ->label('Save changes')
                ->icon('heroicon-o-check')
                ->color('info')
                ->keyBindings(['mod+s'])
                ->formId('form'),
        ];
    }

}