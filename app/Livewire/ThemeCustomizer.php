<?php

namespace App\Livewire;

use App\Cms\Core\CmsCacheVersions;
use App\Cms\Core\Settings;
use App\Cms\Themes\ThemeManager;
use App\Models\Media;
use App\Models\Post;
use Illuminate\Support\Collection;
use Livewire\Attributes\On;
use Livewire\Component;

class ThemeCustomizer extends Component
{
    public string $theme = 'default';

    public string $screen = 'root';
    public ?string $section = null;
    public ?string $subsection = null;

    public string $preview = 'home'; // home|post|page
    public ?int $previewId = null;
    public string $device = 'desktop';
    public int $cacheBust = 0;

    /** @var array<string,mixed> */
    public array $data = [];

    /** @var array<int, array{id:int,title:string,slug:string}> */
    public array $postOptions = [];

    /** @var array<int, array{id:int,title:string,slug:string}> */
    public array $pageOptions = [];

    // reuse CMS media browser
    public string $mediaTargetKey = '';
    public string $mediaType = 'image';

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
        $saved = is_array($saved) ? $saved : [];

        $draft = session()->get("theme_customizer.draft.{$this->theme}", []);
        $draft = is_array($draft) ? $draft : [];

        $this->data = array_replace_recursive(
            $this->defaults($settings),
            $saved,
            $draft
        );

        $this->postOptions = Post::query()
            ->where('type', 'post')
            ->where('status', 'published')
            ->latest('id')
            ->limit(20)
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
            ->limit(50)
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

    protected function defaults(Settings $settings): array
    {
        $siteName = (string) $settings->get('site_name', config('app.name', 'My CMS'), 'core');
        $homepageId = $settings->get('homepage_page_id', null, 'core');

        return [
            'site_identity' => [
                'site_title' => $siteName,
                'tagline' => '',
                'site_icon_media_id' => null,
                'logo_media_id' => null,
                'logo_width' => 200,
            ],

            'typography' => [
                'headings' => [
                    'font_family' => 'Inter',
                    'font_size' => 'inherit',
                    'font_weight' => '700',
                    'text_transform' => 'none',
                    'line_height' => '1.2',
                    'letter_spacing' => '0',
                    'color' => '',
                ],
                'strong' => [
                    'font_family' => 'Inter',
                    'font_weight' => '700',
                    'color' => '',
                ],
                'paragraph' => [
                    'font_family' => 'Inter',
                    'font_size' => '16px',
                    'font_weight' => '400',
                    'line_height' => '1.7',
                    'letter_spacing' => '0',
                    'color' => '',
                ],
                'list' => [
                    'font_family' => 'Inter',
                    'font_size' => '16px',
                    'line_height' => '1.7',
                    'color' => '',
                ],
                'anchor' => [
                    'font_family' => 'Inter',
                    'color' => '#0f5e9c',
                    'hover_color' => '#0b4c80',
                    'text_decoration' => 'underline',
                ],
            ],

            'homepage' => [
                'mode' => $homepageId ? 'static_page' : 'latest_posts',
                'page_id' => $homepageId ? (int) $homepageId : null,
            ],

            'footer' => [
                'background_color' => '#ffffff',
                'text_color' => '#111827',
                'before_copyright' => '',
                'copyright_area' => '[Y] Your Garments Manufacturing Company. All rights reserved.',
                'second_line' => 'Production Base: Bangladesh | Operations: Canada',
                'text_alignment' => 'center',
            ],

            'additional_css' => '',

            'appearance' => [
                'header_layout' => 'left',
                'header_sticky' => true,
                'background' => '#ffffff',
                'text' => '#111827',
                'primary' => '#f59e0b',
                'accent' => '#0ea5e9',
                'container_width' => 'default',
                'rounded' => true,
                'shadows' => true,
            ],
        ];
    }

    public function updated($name, $value): void
    {
        if (
            str_starts_with($name, 'data.') ||
            in_array($name, ['preview', 'previewId', 'device', 'screen', 'section', 'subsection'], true)
        ) {
            if ($name === 'preview') {
                if ($this->preview === 'post') {
                    $this->previewId = $this->postOptions[0]['id'] ?? null;
                } elseif ($this->preview === 'page') {
                    $this->previewId = $this->pageOptions[0]['id'] ?? null;
                } else {
                    $this->previewId = null;
                }
            }

            $this->syncCoreSettings();

            if (str_starts_with($name, 'data.homepage.')) {
                $this->preview = 'home';
                $this->previewId = null;
            }

            $this->saveDraft();
            $this->refreshPreview();
        }
    }

    protected function syncCoreSettings(): void
    {
        $mode = (string) data_get($this->data, 'homepage.mode', 'latest_posts');
        $pageId = data_get($this->data, 'homepage.page_id');

        if ($mode !== 'static_page') {
            data_set($this->data, 'homepage.page_id', null);
        } elseif (is_numeric($pageId)) {
            data_set($this->data, 'homepage.page_id', (int) $pageId);
        }
    }

    public function openSection(string $section): void
    {
        $this->screen = 'section';
        $this->section = $section;
        $this->subsection = null;
    }

    public function openSubsection(string $section, string $subsection): void
    {
        $this->screen = 'subsection';
        $this->section = $section;
        $this->subsection = $subsection;
    }

    public function openThemeBrowser(): void
    {
        $this->screen = 'themes';
        $this->section = null;
        $this->subsection = null;
    }

    public function goRoot(): void
    {
        $this->screen = 'root';
        $this->section = null;
        $this->subsection = null;
    }

    public function openMediaBrowser(string $targetKey, string $type = 'image'): void
    {
        $this->mediaTargetKey = $targetKey;
        $this->mediaType = $type;

        $this->dispatch(
            'cms-media-browser-open',
            targetKey: $targetKey,
            type: $type,
            source: 'theme-customizer'
        );
    }

    #[On('cms-media-selected')]
    public function handleCmsMediaSelected($mediaId = null, $targetKey = null, $source = null): void
    {
        if ($source !== null && $source !== 'theme-customizer') {
            return;
        }

        $resolvedTargetKey = is_string($targetKey) && $targetKey !== ''
            ? $targetKey
            : $this->mediaTargetKey;

        if ($resolvedTargetKey === '') {
            return;
        }

        $mediaId = (int) $mediaId;
        if ($mediaId <= 0) {
            return;
        }

        $media = Media::query()->with('variantRecords')->whereKey($mediaId)->first();
        if (!$media) {
            return;
        }

        if ($this->mediaType === 'image' && method_exists($media, 'isImage') && !$media->isImage()) {
            session()->flash('customizer_notice', 'Please select an image file.');

            $this->dispatch('notify', type: 'error', message: 'Please select an image file.');
            $this->dispatch('customizer-notice', message: 'Please select an image file.', type: 'error');

            return;
        }

        data_set($this->data, $resolvedTargetKey, $mediaId);

        $this->saveDraft();
        $this->refreshPreview();

        session()->flash('customizer_notice', 'Media selected.');

        $this->dispatch('notify', type: 'success', message: 'Media selected.');
        $this->dispatch('customizer-notice', message: 'Media selected.', type: 'success');
    }

    public function clearMedia(string $targetKey): void
    {
        data_set($this->data, $targetKey, null);

        $this->saveDraft();
        $this->refreshPreview();

        session()->flash('customizer_notice', 'Media removed.');

        $this->dispatch('notify', type: 'success', message: 'Media removed.');
        $this->dispatch('customizer-notice', message: 'Media removed.', type: 'success');
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

    public function publish(): void
    {
        /** @var Settings $settings */
        $settings = app(Settings::class);

        /** @var CmsCacheVersions $versions */
        $versions = app(CmsCacheVersions::class);

        $this->syncCoreSettings();

        $settings->set("theme_options.{$this->theme}", $this->data);

        $siteTitle = (string) data_get($this->data, 'site_identity.site_title', '');
        $homepageMode = (string) data_get($this->data, 'homepage.mode', 'latest_posts');
        $homepagePageId = data_get($this->data, 'homepage.page_id');

        $settings->set('site_name', $siteTitle, 'core');
        $settings->set(
            'homepage_page_id',
            $homepageMode === 'static_page' ? $homepagePageId : null,
            'core'
        );

        session()->put("theme_customizer.draft.{$this->theme}", $this->data);

        $versions->bumpRender();

        session()->flash('customizer_notice', 'Published successfully.');

        $this->dispatch('notify', type: 'success', message: 'Published successfully.');
        $this->dispatch('customizer-notice', message: 'Published successfully.', type: 'success');

        $this->refreshPreview();
    }

    public function resetDraft(): void
    {
        /** @var Settings $settings */
        $settings = app(Settings::class);

        session()->forget("theme_customizer.draft.{$this->theme}");

        $saved = $settings->get("theme_options.{$this->theme}", []);
        $saved = is_array($saved) ? $saved : [];

        $this->data = array_replace_recursive($this->defaults($settings), $saved);

        $this->saveDraft();

        session()->flash('customizer_notice', 'Draft reset.');

        $this->dispatch('notify', type: 'success', message: 'Draft reset.');
        $this->dispatch('customizer-notice', message: 'Draft reset.', type: 'success');

        $this->refreshPreview();
    }

    public function getPreviewUrlProperty(): string
    {
        $base = '?customizer=1&preview_theme=' . urlencode($this->theme) . '&_t=' . $this->cacheBust;

        if ($this->preview === 'post') {
            $id = $this->previewId ?: ($this->postOptions[0]['id'] ?? null);
            $post = collect($this->postOptions)->firstWhere('id', (int) $id);
            $slug = is_array($post) ? ($post['slug'] ?? null) : null;

            return $slug ? url('/posts/' . $slug) . $base : url('/') . $base;
        }

        if ($this->preview === 'page') {
            $id = $this->previewId ?: ($this->pageOptions[0]['id'] ?? null);
            $page = collect($this->pageOptions)->firstWhere('id', (int) $id);
            $slug = is_array($page) ? ($page['slug'] ?? null) : null;

            return $slug ? url('/pages/' . $slug) . $base : url('/') . $base;
        }

        return url('/') . $base;
    }

    public function getIframeWidthClassProperty(): string
    {
        return match ($this->device) {
            'mobile' => 'max-w-[390px]',
            'tablet' => 'max-w-[820px]',
            default => 'max-w-full',
        };
    }

    public function getThemesProperty(): array
    {
        /** @var ThemeManager $themes */
        $themes = app(ThemeManager::class);

        return $themes->discoverForUi();
    }

    public function getFontFamilyOptionsProperty(): array
    {
        return [
            'Inter' => 'Inter',
            'Roboto' => 'Roboto',
            'Ropa Sans' => 'Ropa Sans',
            'Arial' => 'Arial',
            'Georgia' => 'Georgia',
            'System UI' => 'System UI',
        ];
    }

    public function getSelectedMediaProperty(): Collection
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', [
            data_get($this->data, 'site_identity.logo_media_id'),
            data_get($this->data, 'site_identity.site_icon_media_id'),
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

    public function getLogoUrlProperty(): ?string
    {
        $id = data_get($this->data, 'site_identity.logo_media_id');
        if (!$id) {
            return null;
        }

        $media = Media::query()->with('variantRecords')->whereKey((int) $id)->first();

        return $media ? ($media->variantUrl('medium') ?: $media->url()) : null;
    }

    public function getSiteIconUrlProperty(): ?string
    {
        $id = data_get($this->data, 'site_identity.site_icon_media_id');
        if (!$id) {
            return null;
        }

        $media = Media::query()->with('variantRecords')->whereKey((int) $id)->first();

        return $media ? ($media->variantUrl('medium') ?: $media->url()) : null;
    }

    public function activateTheme(string $slug): void
    {
        /** @var ThemeManager $themes */
        $themes = app(ThemeManager::class);

        if (!isset($themes->all()[$slug])) {
            return;
        }

        $this->theme = $slug;

        /** @var Settings $settings */
        $settings = app(Settings::class);

        $saved = $settings->get("theme_options.{$this->theme}", []);
        $saved = is_array($saved) ? $saved : [];

        $draft = session()->get("theme_customizer.draft.{$this->theme}", []);
        $draft = is_array($draft) ? $draft : [];

        $this->data = array_replace_recursive($this->defaults($settings), $saved, $draft);

        $this->preview = 'home';
        $this->previewId = null;

        $this->saveDraft();
        $this->refreshPreview();
        $this->goRoot();

        session()->flash('customizer_notice', 'Theme loaded in customizer.');

        $this->dispatch('notify', type: 'success', message: 'Theme loaded in customizer.');
        $this->dispatch('customizer-notice', message: 'Theme loaded in customizer.', type: 'success');
    }

    public function render()
    {
        return view('livewire.theme-customizer', [
            'logoUrl' => $this->logoUrl,
            'siteIconUrl' => $this->siteIconUrl,
            'themes' => $this->themes,
            'fontFamilyOptions' => $this->fontFamilyOptions,
        ]);
    }
}