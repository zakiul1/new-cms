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

    public string $mediaTargetKey = '';
    public bool $showMediaPicker = false;

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
            ->map(fn ($p) => [
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
            ->map(fn ($p) => [
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

        $this->normalizeCustomizerState();
        $this->saveDraft();
        $this->cacheBust = time();
    }

    protected function defaults(Settings $settings): array
    {
        $siteName = (string) $settings->get('site_name', config('app.name', 'My CMS'), 'core');
        $homepageId = $settings->get('homepage_page_id', null, 'core');
        $defaultFont = theme_default_font_family($this->theme);

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
                    'font_family' => $defaultFont,
                    'font_size' => 'inherit',
                    'font_weight' => '700',
                    'text_transform' => 'none',
                    'line_height' => '1.2',
                    'letter_spacing' => '0',
                    'color' => '',
                ],
                'strong' => [
                    'font_family' => $defaultFont,
                    'font_weight' => '700',
                    'color' => '',
                ],
                'paragraph' => [
                    'font_family' => $defaultFont,
                    'font_size' => '16px',
                    'font_weight' => '400',
                    'line_height' => '1.7',
                    'letter_spacing' => '0',
                    'color' => '',
                ],
                'list' => [
                    'font_family' => $defaultFont,
                    'font_size' => '16px',
                    'line_height' => '1.7',
                    'color' => '',
                ],
                'anchor' => [
                    'font_family' => $defaultFont,
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
                'heading' => '',
                'description' => '',
                'button_text' => '',
                'button_url' => '',
                'show_menu' => true,
                'show_widgets' => true,
                'show_bottom_content' => true,
            ],

            'additional_css' => '',

            'appearance' => [
                'header_layout' => 'left',
                'header_sticky' => true,
                'background' => '#ffffff',
                'text' => '#111827',
                'primary' => '#2f6fa3',
                'accent' => '#0ea5e9',
                'cms_container_width' => '1280px',
                'page_container_width' => '1140px',
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

            $this->normalizeCustomizerState();
            $this->syncCoreSettings();

            if (str_starts_with($name, 'data.homepage.')) {
                $this->preview = 'home';
                $this->previewId = null;
            }

            $this->saveDraft();
            $this->refreshPreview();
        }
    }

    protected function normalizeCustomizerState(): void
    {
        if ($this->section === 'menus') {
            $this->goRoot();
        }

        $logoWidth = (int) data_get($this->data, 'site_identity.logo_width', 200);
        $logoWidth = max(20, min(600, $logoWidth));
        data_set($this->data, 'site_identity.logo_width', $logoWidth);

        foreach ([
            'site_identity.logo_media_id',
            'site_identity.site_icon_media_id',
            'homepage.page_id',
        ] as $key) {
            $value = data_get($this->data, $key);

            if ($value === '' || $value === false) {
                data_set($this->data, $key, null);
                continue;
            }

            if (is_numeric($value)) {
                data_set($this->data, $key, (int) $value);
            }
        }

        foreach ([
            'footer.show_menu',
            'footer.show_widgets',
            'footer.show_bottom_content',
            'appearance.header_sticky',
            'appearance.rounded',
            'appearance.shadows',
        ] as $key) {
            data_set($this->data, $key, (bool) data_get($this->data, $key, false));
        }

        data_set(
            $this->data,
            'appearance.cms_container_width',
            $this->normalizeCssLength(
                data_get($this->data, 'appearance.cms_container_width'),
                '1280px'
            )
        );

        data_set(
            $this->data,
            'appearance.page_container_width',
            $this->normalizeCssLength(
                data_get($this->data, 'appearance.page_container_width'),
                '1140px'
            )
        );
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

    protected function normalizeCssLength(mixed $value, string $fallback): string
    {
        if ($value === null) {
            return $fallback;
        }

        $value = trim((string) $value);

        if ($value === '') {
            return $fallback;
        }

        if (preg_match('/^\d+(\.\d+)?$/', $value)) {
            return $value . 'px';
        }

        if (preg_match('/^\d+(\.\d+)?(px|%|rem|em|vw|vh|vmin|vmax|ch|ex)$/i', $value)) {
            return $value;
        }

        if (preg_match('/^(auto|inherit|initial|unset|min-content|max-content|fit-content)$/i', $value)) {
            return $value;
        }

        if (
            str_starts_with($value, 'calc(') ||
            str_starts_with($value, 'clamp(') ||
            str_starts_with($value, 'min(') ||
            str_starts_with($value, 'max(') ||
            str_starts_with($value, 'var(')
        ) {
            return $value;
        }

        return $fallback;
    }

    public function openSection(string $section): void
    {
        if ($section === 'menus') {
            $this->goRoot();
            return;
        }

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

    public function closeCustomizer()
    {
        return redirect()->to('/lara-admin/themes');
    }

    public function openMediaPicker(string $targetKey): void
    {
        $this->mediaTargetKey = $targetKey;
        $this->showMediaPicker = true;
    }

    public function closeMediaPicker(): void
    {
        $this->showMediaPicker = false;
    }

    #[On('media-library-apply')]
    public function handleMediaLibraryApply(array $ids = [], ?string $statePath = null): void
    {
        if ($statePath !== 'theme-customizer-media') {
            return;
        }

        $resolvedTargetKey = $this->mediaTargetKey;

        if ($resolvedTargetKey === '') {
            return;
        }

        $mediaId = (int) ($ids[0] ?? 0);
        if ($mediaId <= 0) {
            return;
        }

        $media = Media::query()
            ->with('variantRecords')
            ->whereKey($mediaId)
            ->first();

        if (!$media) {
            session()->flash('customizer_notice', 'Selected media was not found.');

            $this->dispatch('notify', type: 'error', message: 'Selected media was not found.');
            $this->dispatch('customizer-notice', message: 'Selected media was not found.', type: 'error');

            return;
        }

        if (method_exists($media, 'isImage') && !$media->isImage()) {
            session()->flash('customizer_notice', 'Please select an image file.');

            $this->dispatch('notify', type: 'error', message: 'Please select an image file.');
            $this->dispatch('customizer-notice', message: 'Please select an image file.', type: 'error');

            return;
        }

        data_set($this->data, $resolvedTargetKey, $mediaId);

        $this->normalizeCustomizerState();
        $this->saveDraft();
        $this->refreshPreview();
        $this->showMediaPicker = false;

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

        $this->normalizeCustomizerState();
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

        $this->normalizeCustomizerState();
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
        return theme_font_choices($this->theme);
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
        $this->showMediaPicker = false;
        $this->mediaTargetKey = '';

        $this->normalizeCustomizerState();
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