<?php

namespace App\Livewire;

use App\Cms\Core\Settings;
use App\Models\Post;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Component;
use Livewire\WithFileUploads;

class ThemeCustomizer extends Component
{
    use WithFileUploads;

    public string $theme = 'default';

    // home | post | page
    public string $preview = 'home';

    // selected item id (when preview = post/page)
    public ?int $previewId = null;

    // search in panels (accordion UI)
    public string $panelSearch = '';

    // desktop | tablet | mobile
    public string $device = 'desktop';

    // temp upload
    public $logoUpload = null;

    public array $data = [];

    public int $cacheBust = 0;

    /** @var array<int, array{id:int,title:string,slug:string}> */
    public array $postOptions = [];

    /** @var array<int, array{id:int,title:string,slug:string}> */
    public array $pageOptions = [];

    public function mount(string $theme = 'default', Settings $settings): void
    {
        $this->theme = $theme ?: 'default';

        $saved = $settings->get("theme_options.{$this->theme}", []);

        $defaults = [
            // base
            'primary' => '#f59e0b',
            'accent' => '#0ea5e9',
            'background' => '#ffffff',
            'text' => '#111827',
            'font_family' => 'system',
            'base_font_size' => 16,
            'container_width' => 'default',
            'rounded' => true,
            'shadows' => true,
            'custom_css' => '',

            // ✅ Header (premium)
            'header_layout' => 'left', // left | center | split
            'header_sticky' => true,
            'header_bg' => '#ffffff',
            'header_text' => '#111827',
            'logo_path' => null,       // stored in public disk
            'logo_width' => 140,       // px
        ];

        $draft = session()->get("theme_customizer.draft.{$this->theme}", []);
        $source = (is_array($draft) && !empty($draft))
            ? $draft
            : (is_array($saved) ? $saved : []);

        $this->data = array_merge($defaults, $source);

        // Load preview items (latest 10)
        $this->postOptions = Post::query()
            ->where('type', 'post')
            ->where('status', 'published')
            ->latest('id')
            ->limit(10)
            ->get(['id', 'title', 'slug'])
            ->map(fn($p) => [
                'id' => (int) $p->id,
                'title' => (string) $p->title,
                'slug' => (string) $p->slug,
            ])
            ->all();

        $this->pageOptions = Post::query()
            ->where('type', 'page')
            ->where('status', 'published')
            ->latest('id')
            ->limit(10)
            ->get(['id', 'title', 'slug'])
            ->map(fn($p) => [
                'id' => (int) $p->id,
                'title' => (string) $p->title,
                'slug' => (string) $p->slug,
            ])
            ->all();

        // Default selected item
        if ($this->preview === 'post' && $this->previewId === null) {
            $this->previewId = $this->postOptions[0]['id'] ?? null;
        }
        if ($this->preview === 'page' && $this->previewId === null) {
            $this->previewId = $this->pageOptions[0]['id'] ?? null;
        }

        $this->saveDraft();
        $this->cacheBust = time();
    }

    public function updated($name, $value): void
    {
        if (str_starts_with($name, 'data.') || in_array($name, ['preview', 'previewId', 'device'], true)) {

            // If preview type changed, choose default item for that type
            if ($name === 'preview') {
                if ($this->preview === 'post') {
                    $this->previewId = $this->postOptions[0]['id'] ?? null;
                } elseif ($this->preview === 'page') {
                    $this->previewId = $this->pageOptions[0]['id'] ?? null;
                } else {
                    $this->previewId = null;
                }
            }

            $this->saveDraft();
            $this->refreshPreview();
        }
    }

    /** ✅ Handles logo upload automatically when user selects a file */
    public function updatedLogoUpload(): void
    {
        if (!$this->logoUpload) {
            return;
        }

        $this->validate([
            'logoUpload' => 'image|max:2048', // 2MB
        ]);

        /** @var UploadedFile $file */
        $file = $this->logoUpload;

        // store on "public" disk
        $path = $file->store("themes-assets/{$this->theme}/logo", 'public');

        // delete old logo if exists
        $old = $this->data['logo_path'] ?? null;
        if (is_string($old) && $old !== '' && $old !== $path) {
            Storage::disk('public')->delete($old);
        }

        $this->data['logo_path'] = $path;

        // clear temp upload
        $this->logoUpload = null;

        $this->saveDraft();
        $this->refreshPreview();
    }

    public function removeLogo(): void
    {
        $path = $this->data['logo_path'] ?? null;

        if (is_string($path) && $path !== '') {
            Storage::disk('public')->delete($path);
        }

        $this->data['logo_path'] = null;

        $this->saveDraft();
        $this->refreshPreview();
    }

    public function refreshPreview(): void
    {
        $this->cacheBust = time();
        $this->dispatch('customizer-refresh');
    }

    public function saveDraft(): void
    {
        session()->put("theme_customizer.draft.{$this->theme}", $this->data);
    }

    public function publish(Settings $settings): void
    {
        $settings->set("theme_options.{$this->theme}", $this->data);

        session()->forget("theme_customizer.draft.{$this->theme}");
        $this->saveDraft();

        session()->flash('customizer_notice', 'Published successfully.');
        $this->refreshPreview();
    }

    public function resetDraft(Settings $settings): void
    {
        session()->forget("theme_customizer.draft.{$this->theme}");

        $saved = $settings->get("theme_options.{$this->theme}", []);
        $this->data = is_array($saved) ? $saved : [];

        $this->saveDraft();

        session()->flash('customizer_notice', 'Draft reset.');
        $this->refreshPreview();
    }

    public function getPreviewUrlProperty(): string
    {
        // Home
        if ($this->preview === 'home') {
            return url('/')
                . '?customizer=1&preview_theme=' . urlencode($this->theme)
                . '&_t=' . $this->cacheBust;
        }

        // Post
        if ($this->preview === 'post') {
            $id = $this->previewId ?: ($this->postOptions[0]['id'] ?? null);
            if (!$id) {
                return url('/') . '?customizer=1&preview_theme=' . urlencode($this->theme) . '&_t=' . $this->cacheBust;
            }

            $slug = collect($this->postOptions)->firstWhere('id', (int) $id)['slug'] ?? null;
            if (!$slug) {
                $p = Post::query()->whereKey($id)->first();
                $slug = $p?->slug;
            }

            if ($slug) {
                return url('/posts/' . $slug)
                    . '?customizer=1&preview_theme=' . urlencode($this->theme)
                    . '&_t=' . $this->cacheBust;
            }
        }

        // Page
        if ($this->preview === 'page') {
            $id = $this->previewId ?: ($this->pageOptions[0]['id'] ?? null);
            if (!$id) {
                return url('/') . '?customizer=1&preview_theme=' . urlencode($this->theme) . '&_t=' . $this->cacheBust;
            }

            $slug = collect($this->pageOptions)->firstWhere('id', (int) $id)['slug'] ?? null;
            if (!$slug) {
                $p = Post::query()->whereKey($id)->first();
                $slug = $p?->slug;
            }

            if ($slug) {
                return url('/pages/' . $slug)
                    . '?customizer=1&preview_theme=' . urlencode($this->theme)
                    . '&_t=' . $this->cacheBust;
            }
        }

        // fallback
        return url('/')
            . '?customizer=1&preview_theme=' . urlencode($this->theme)
            . '&_t=' . $this->cacheBust;
    }

    public function getIframeWidthClassProperty(): string
    {
        return match ($this->device) {
            'mobile' => 'max-w-[390px]',
            'tablet' => 'max-w-[820px]',
            default => 'max-w-full',
        };
    }

    public function render()
    {
        return view('livewire.theme-customizer')
            ->layout('layouts.customizer');
    }
}