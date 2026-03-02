<?php

namespace Plugins\MultiPage\Filament\Pages;

use App\Models\Post;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Storage;
use Plugins\MultiPage\Support\MultiPageStorage;

class MultiPageLinksPage extends Page
{
    /**
     * Must match base type: route has param.
     */
    protected static ?string $slug = 'multi-page/links/{pageId}';

    /**
     * Do not show in sidebar (it needs a parameter).
     */
    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    protected string $view = 'multi-page::filament.pages.links-list';
    protected static ?string $title = 'Multi Page Links';

    public ?int $pageId = null;
    public ?Post $page = null;
    public array $links = [];

    public function mount(?int $pageId = null): void
    {
        $this->pageId = $pageId ?: null;

        if ($this->pageId === null) {
            $this->page = null;
            $this->links = [];
            return;
        }

        // multipage CPT
        $this->page = Post::query()
            ->whereKey($this->pageId)
            ->where('type', 'multipage')
            ->first();

        $this->loadLinks();
    }

    public function loadLinks(): void
    {
        $this->links = [];

        if (!$this->page) {
            return;
        }

        MultiPageStorage::ensureDirs();

        $disk = Storage::disk('local');

        // tracker key must match generator behavior
        $trackerKey = (string) ($this->page->slug ?? '');
        if (trim($trackerKey) === '') {
            $trackerKey = 'multipage-' . (int) $this->page->id;
        }

        $trackerPath = MultiPageStorage::TRACKERS . '/' . $trackerKey . '.json';

        if (!$disk->exists($trackerPath)) {
            return;
        }

        $decoded = json_decode((string) $disk->get($trackerPath), true);
        if (!is_array($decoded)) {
            return;
        }

        $generated = $decoded['generated'] ?? [];
        $this->links = is_array($generated) ? array_values($generated) : [];
    }
}