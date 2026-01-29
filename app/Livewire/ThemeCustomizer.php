<?php

namespace App\Livewire;

use App\Cms\Core\CmsCacheVersions;
use App\Cms\Core\Settings;
use App\Cms\Themes\ThemeManager;
use App\Models\Media;
use App\Models\Post;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Livewire\Component;
use Livewire\WithPagination;

class ThemeCustomizer extends Component
{
    use WithPagination;

    public string $theme = 'default';

    // home | post | page
    public string $preview = 'home';

    public ?int $previewId = null;

    public string $panelSearch = '';

    // desktop | tablet | mobile
    public string $device = 'desktop';

    public array $data = [];

    public int $cacheBust = 0;

    /** @var array<int, array{id:int,title:string,slug:string}> */
    public array $postOptions = [];

    /** @var array<int, array{id:int,title:string,slug:string}> */
    public array $pageOptions = [];

    // -----------------------------
    // Media picker modal state (CUSTOM)
    // -----------------------------
    public bool $mediaPickerOpen = false;
    public string $mediaSearch = '';
    public string $mediaTargetKey = ''; // e.g. "logo_media_id"
    public string $mediaType = 'image'; // image | any

    public function mount(?string $theme = 'default'): void
    {
        abort_unless(auth()->check(), 403);

        /** @var ThemeManager $themes */
        $themes = app(ThemeManager::class);

        /** @var Settings $settings */
        $settings = app(Settings::class);

        $this->theme = $theme ?: 'default';

        $all = $themes->all();
        if (!isset($all[$this->theme])) {
            $this->theme = $themes->activeSlug();
        }

        $saved = $settings->get("theme_options.{$this->theme}", []);

        $defaults = [
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

            'header_layout' => 'left', // left | center | split
            'header_sticky' => true,
            'header_bg' => '#ffffff',
            'header_text' => '#111827',

            // media references
            'logo_media_id' => null,
            'favicon_media_id' => null,

            // backward compatibility
            'logo_path' => null,

            // optional if used in UI
            'logo_width' => 140,
        ];

        $draft = session()->get("theme_customizer.draft.{$this->theme}", []);
        $source = (is_array($draft) && !empty($draft))
            ? $draft
            : (is_array($saved) ? $saved : []);

        $this->data = array_merge($defaults, $source);

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

    // ---------------------------------------
    // Media Picker (CUSTOM)
    // ---------------------------------------

    public function openMediaPicker(string $targetKey, string $type = 'image'): void
    {
        $this->mediaTargetKey = $targetKey;
        $this->mediaType = $type;
        $this->mediaSearch = '';
        $this->mediaPickerOpen = true;

        $this->resetPage('mediaPage');
    }

    public function closeMediaPicker(): void
    {
        $this->mediaPickerOpen = false;
        $this->mediaSearch = '';
        $this->mediaTargetKey = '';
        $this->mediaType = 'image';

        $this->resetPage('mediaPage');
    }

    public function updatedMediaSearch(): void
    {
        $this->resetPage('mediaPage');
    }

    public function selectMedia(int $mediaId): void
    {
        if ($this->mediaTargetKey === '') {
            return;
        }

        $m = Media::query()->whereKey($mediaId)->first();
        if (!$m) {
            return;
        }

        if ($this->mediaType === 'image' && !$m->isImage()) {
            return;
        }

        $this->data[$this->mediaTargetKey] = (int) $m->id;

        $this->saveDraft();
        $this->refreshPreview();
        $this->closeMediaPicker();
    }

    public function clearMedia(string $targetKey): void
    {
        $this->data[$targetKey] = null;
        $this->saveDraft();
        $this->refreshPreview();
    }

    public function getMediaPickerProperty(): ?LengthAwarePaginator
    {
        if (!$this->mediaPickerOpen) {
            return null;
        }

        $q = trim($this->mediaSearch);

        $query = Media::query()
            ->with('variantRecords')
            ->latest('id');

        if ($this->mediaType === 'image') {
            $query->where('mime_type', 'like', 'image/%');
        }

        if ($q !== '') {
            $query->where(function ($qq) use ($q) {
                $qq->where('title', 'like', "%{$q}%")
                    ->orWhere('original_filename', 'like', "%{$q}%")
                    ->orWhere('filename', 'like', "%{$q}%");
            });
        }

        return $query->paginate(24, ['*'], 'mediaPage');
    }

    public function getSelectedMediaProperty(): Collection
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', [
            $this->data['logo_media_id'] ?? null,
            $this->data['favicon_media_id'] ?? null,
        ]))));

        if (!$ids) {
            return collect();
        }

        return Media::query()
            ->with('variantRecords')
            ->whereIn('id', $ids)
            ->get()
            ->keyBy('id');
    }

    // ---------------------------------------
    // Preview
    // ---------------------------------------

    public function refreshPreview(): void
    {
        $this->cacheBust = time();
        $this->dispatch('customizer-refresh');
    }

    public function saveDraft(): void
    {
        session()->put("theme_customizer.draft.{$this->theme}", $this->data);
    }

    public function publish(): void
    {
        /** @var Settings $settings */
        $settings = app(Settings::class);

        /** @var CmsCacheVersions $versions */
        $versions = app(CmsCacheVersions::class);

        $settings->set("theme_options.{$this->theme}", $this->data);

        session()->forget("theme_customizer.draft.{$this->theme}");
        $this->saveDraft();

        $versions->bumpRender();

        session()->flash('customizer_notice', 'Published successfully.');
        $this->refreshPreview();
    }

    public function resetDraft(): void
    {
        /** @var Settings $settings */
        $settings = app(Settings::class);

        session()->forget("theme_customizer.draft.{$this->theme}");

        $saved = $settings->get("theme_options.{$this->theme}", []);
        $this->data = is_array($saved) ? $saved : [];

        $this->saveDraft();

        session()->flash('customizer_notice', 'Draft reset.');
        $this->refreshPreview();
    }

    public function getPreviewUrlProperty(): string
    {
        if ($this->preview === 'home') {
            return url('/') . '?customizer=1&preview_theme=' . urlencode($this->theme) . '&_t=' . $this->cacheBust;
        }

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

            return $slug
                ? url('/posts/' . $slug) . '?customizer=1&preview_theme=' . urlencode($this->theme) . '&_t=' . $this->cacheBust
                : url('/') . '?customizer=1&preview_theme=' . urlencode($this->theme) . '&_t=' . $this->cacheBust;
        }

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

            return $slug
                ? url('/pages/' . $slug) . '?customizer=1&preview_theme=' . urlencode($this->theme) . '&_t=' . $this->cacheBust
                : url('/') . '?customizer=1&preview_theme=' . urlencode($this->theme) . '&_t=' . $this->cacheBust;
        }

        return url('/') . '?customizer=1&preview_theme=' . urlencode($this->theme) . '&_t=' . $this->cacheBust;
    }

    public function getIframeWidthClassProperty(): string
    {
        return match ($this->device) {
            'mobile' => 'max-w-[390px]',
            'tablet' => 'max-w-[820px]',
            default => 'max-w-full',
        };
    }

    // ---------------------------------------
    // Helpers for views (logo/favicon URLs)
    // ---------------------------------------

    public function getLogoUrlProperty(): ?string
    {
        $id = $this->data['logo_media_id'] ?? null;
        if ($id) {
            $m = Media::query()->with('variantRecords')->whereKey((int) $id)->first();
            return $m ? ($m->variantUrl('medium') ?: $m->url()) : null;
        }

        $path = $this->data['logo_path'] ?? null;
        if (is_string($path) && $path !== '') {
            return asset('storage/' . ltrim($path, '/'));
        }

        return null;
    }

    public function getFaviconUrlProperty(): ?string
    {
        $id = $this->data['favicon_media_id'] ?? null;
        if (!$id) {
            return null;
        }

        $m = Media::query()->with('variantRecords')->whereKey((int) $id)->first();
        return $m?->url();
    }

    public function render()
    {
        return view('livewire.theme-customizer', [
            'mediaPicker' => $this->mediaPicker, // ✅ paginator or null
            'logoUrl' => $this->logoUrl,
            'faviconUrl' => $this->faviconUrl,
        ]);
    }
}