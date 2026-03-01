<?php

namespace Plugins\MultiPage\Filament\Pages;

use App\Models\Post;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Storage;
use Plugins\MultiPage\Support\MultiPageStorage;

class MultiPageLinksPage extends Page
{
    // Must match base type
    protected static ?string $slug = 'multi-page/links/{pageId}';

    // ✅ IMPORTANT: do not show in sidebar (it needs a parameter)
    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public string $view = 'multi-page::filament.pages.links-list';
    protected static ?string $title = 'Multi Page Links';

    public ?int $pageId = null;
    public ?Post $page = null;
    public array $links = [];

    public function mount(?int $pageId = null): void
    {
        $this->pageId = $pageId;

        if (!$this->pageId) {
            $this->page = null;
            $this->links = [];
            return;
        }

        $this->page = Post::query()
            ->whereKey($this->pageId)
            ->where('type', 'page')
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

        $trackerPath = MultiPageStorage::TRACKERS . '/' . $this->page->slug . '.json';
        if (!Storage::disk('local')->exists($trackerPath)) {
            return;
        }

        $json = json_decode((string) Storage::disk('local')->get($trackerPath), true);
        if (!is_array($json)) {
            return;
        }

        $generated = $json['generated'] ?? [];
        $this->links = is_array($generated) ? array_values($generated) : [];
    }
}