<?php

namespace App\Filament\Pages;

use App\Cms\Core\CmsCacheVersions;
use App\Cms\Core\SettingsRepository;
use App\Cms\Themes\ThemeManager;
use App\Models\Post;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Cache;
use Throwable;

class ManageCmsSettings extends Page
{
    protected static ?string $navigationLabel = 'CMS Settings';
    protected static \UnitEnum|string|null $navigationGroup = 'CMS';
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-cog-8-tooth';

    protected string $view = 'filament.pages.manage-cms-settings';

    /** @var array<string, mixed> | null */
    public ?array $data = [];

    /**
     * WP-like normalization:
     * - If scheme missing -> prepend https://
     * - If www missing -> add www. (skips localhost + IP + dev TLDs)
     * - Trim trailing slash
     * - Keep port if given
     */
    private function normalizeSiteUrl(string $input): string
    {
        $input = trim($input);

        if ($input === '') {
            return rtrim((string) config('app.url'), '/');
        }

        // If user typed only domain, prepend https://
        if (!preg_match('#^https?://#i', $input)) {
            $input = 'https://' . $input;
        }

        $parts = parse_url($input);

        $scheme = $parts['scheme'] ?? 'https';
        $host = $parts['host'] ?? '';

        if ($host === '') {
            return rtrim((string) config('app.url'), '/');
        }

        $lowerHost = strtolower($host);

        // Skip forcing www for IP/localhost/dev domains
        $isIp = filter_var($host, FILTER_VALIDATE_IP) !== false;
        $isLocalhost = in_array($lowerHost, ['localhost', '127.0.0.1'], true);

        // Common dev TLDs / local domains
        $isDevTld =
            str_ends_with($lowerHost, '.test') ||
            str_ends_with($lowerHost, '.local') ||
            str_ends_with($lowerHost, '.localhost');

        // Add www only for real public domains
        if (
            !$isIp &&
            !$isLocalhost &&
            !$isDevTld &&
            !str_starts_with($lowerHost, 'www.')
        ) {
            $host = 'www.' . $host;
        }

        $port = isset($parts['port']) ? ':' . $parts['port'] : '';

        return rtrim($scheme . '://' . $host . $port, '/');
    }

    public function mount(SettingsRepository $settings, ThemeManager $themes): void
    {
        // ✅ Source of truth is ThemeManager (it reads from settings + ensures valid)
        $activeTheme = $themes->activeSlug();

        $homepageId = $settings->get('core', 'homepage_page_id', null);
        $homepageId = is_numeric($homepageId) ? (int) $homepageId : null;
        if ($homepageId !== null && $homepageId <= 0) {
            $homepageId = null;
        }

        $savedSiteUrl = (string) $settings->get('core', 'site_url', rtrim((string) config('app.url'), '/'));
        $siteUrl = $this->normalizeSiteUrl($savedSiteUrl);

        $this->form->fill([
            // Core
            'site_name' => $settings->get('core', 'site_name', 'My CMS'),
            'site_url' => $siteUrl,
            'timezone' => $settings->get('core', 'timezone', config('app.timezone')),
            'active_theme' => $activeTheme,

            // Global Contact (Siatex header)
            'status' => $settings->get('core', 'status', ''),
            'contact_phone' => $settings->get('core', 'contact_phone', ''),
            'contact_email' => $settings->get('core', 'contact_email', ''),

            // Homepage
            'homepage_page_id' => $homepageId,

            // ✅ Permalinks
            'permalink_mode' => $settings->get('core', 'permalink_mode', 'post_name'),
            'permalink_custom_structure' => $settings->get('core', 'permalink_custom_structure', '/%postname%'),
            'category_base' => $settings->get('core', 'category_base', 'category'),
            'tag_base' => $settings->get('core', 'tag_base', 'tag'),

            // ✅ Attachment pages (global)
            'attachment_pages_enabled' => (bool) $settings->get('core', 'attachment_pages_enabled', false),
            'attachment_pages_indexable' => (bool) $settings->get('core', 'attachment_pages_indexable', true),

            // ✅ SEO
            'search_engine_block' => (bool) $settings->get('seo', 'search_engine_block', false),
        ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('clearRenderCache')
                ->label('Clear Render Cache')
                ->color('warning')
                ->icon('heroicon-o-arrow-path')
                ->requiresConfirmation()
                ->action(function (CmsCacheVersions $versions) {
                    $versions->bumpRender();

                    Notification::make()
                        ->success()
                        ->title('Render cache cleared')
                        ->body('Menus/widgets/theme output will be regenerated.')
                        ->send();
                }),

            Action::make('clearThemeDiscovery')
                ->label('Clear Theme Discovery')
                ->color('gray')
                ->icon('heroicon-o-sparkles')
                ->requiresConfirmation()
                ->action(function (ThemeManager $themes) {
                    $themes->forgetDiscoveryCache();

                    Notification::make()
                        ->success()
                        ->title('Theme discovery cache cleared')
                        ->body('Theme list will be re-scanned on next load.')
                        ->send();
                }),

            Action::make('flushCache')
                ->label('Flush ALL Cache')
                ->color('danger')
                ->icon('heroicon-o-trash')
                ->requiresConfirmation()
                ->action(function () {
                    Cache::flush();

                    Notification::make()
                        ->success()
                        ->title('All cache flushed')
                        ->body('All cache entries removed from the cache store.')
                        ->send();
                }),
        ];
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Form::make([
                    TextInput::make('site_name')->required()->maxLength(120),

                    // ✅ Allow domain-only input; normalize on save (WP-like)
                    TextInput::make('site_url')
                        ->required()
                        ->maxLength(255)
                        ->helperText('Example: cms.test OR siatexglobal.com OR https://www.siatexglobal.com'),

                    TextInput::make('timezone')->required()->maxLength(64),

                    TextInput::make('status')
                        ->label('Status')
                        ->maxLength(120),

                    TextInput::make('contact_phone')
                        ->label('Contact Phone')
                        ->maxLength(50),

                    TextInput::make('contact_email')
                        ->label('Contact Email')
                        ->email()
                        ->maxLength(120),

                    Select::make('homepage_page_id')
                        ->label('Homepage Page')
                        ->options(
                            fn() => Post::query()
                                ->where('type', 'page')
                                ->where('status', 'published')
                                ->orderBy('title')
                                ->pluck('title', 'id')
                                ->all()
                        )
                        ->searchable()
                        ->placeholder('— Use latest posts (default) —')
                        ->nullable(),

                    Select::make('active_theme')
                        ->label('Active Theme')
                        ->options(
                            fn(ThemeManager $themes) => collect($themes->all())
                                ->mapWithKeys(fn($m, $slug) => [$slug => ($m->name ?? $slug)])
                                ->all()
                        )
                        ->searchable()
                        ->required()
                        ->reactive(),

                    // ✅ SEO (WP-like)
                    Section::make('SEO')
                        ->description('Control search engine indexing (WordPress-like).')
                        ->collapsible()
                        ->collapsed()
                        ->schema([
                            Toggle::make('search_engine_block')
                                ->label('Discourage search engines from indexing this site')
                                ->helperText('If ON: robots.txt will Disallow /, sitemap will be disabled, and pages will output noindex.')
                                ->default(false),
                        ]),

                    // ✅ Attachment Pages (global) (collapsible)
                    Section::make('Attachment Pages')
                        ->description('Public attachment pages for media at /{media-slug}.')
                        ->collapsible()
                        ->collapsed()
                        ->schema([
                            Toggle::make('attachment_pages_enabled')
                                ->label('Enable attachment pages (public)')
                                ->helperText('If OFF, /{media-slug} will return 404.')
                                ->default(false),

                            Toggle::make('attachment_pages_indexable')
                                ->label('Index attachment pages (SEO)')
                                ->helperText('If OFF, robots meta will be noindex, follow.')
                                ->default(true),
                        ]),

                    // ✅ Permalink Settings (collapsible)
                    Section::make('Permalink Settings')
                        ->description('These rules apply to POSTS. Pages remain /{slug} (WP-style).')
                        ->collapsible()
                        ->collapsed()
                        ->schema([
                            Radio::make('permalink_mode')
                                ->label('Permalink Settings (Posts)')
                                ->options([
                                    'plain' => 'Plain (?p=123)',
                                    'day_name' => 'Day and name (/2026/01/31/sample-post)',
                                    'month_name' => 'Month and name (/2026/01/sample-post)',
                                    'numeric' => 'Numeric (/archives/123)',
                                    'post_name' => 'Post name (/sample-post)',
                                    'custom' => 'Custom Structure',
                                ])
                                ->helperText('Choose how POST URLs are generated.')
                                ->reactive(),

                            TextInput::make('permalink_custom_structure')
                                ->label('Custom Structure')
                                ->placeholder('/%year%/%monthnum%/%day%/%postname%')
                                ->helperText('Allowed tags: %year%, %monthnum%, %day%, %hour%, %minute%, %second%, %post_id%, %postname%')
                                ->visible(fn(Get $get): bool => (string) $get('permalink_mode') === 'custom')
                                ->maxLength(255),

                            TextInput::make('category_base')
                                ->label('Category base')
                                ->placeholder('category')
                                ->helperText('Example: "topics" makes URLs /topics/{category-slug}')
                                ->maxLength(60),

                            TextInput::make('tag_base')
                                ->label('Tag base')
                                ->placeholder('tag')
                                ->helperText('Example: "labels" makes URLs /labels/{tag-slug}')
                                ->maxLength(60),
                        ]),
                ])
                    ->livewireSubmitHandler('save')
                    ->footer([
                        Actions::make([
                            Action::make('save')
                                ->label('Save')
                                ->submit('save')
                                ->keyBindings(['mod+s']),
                        ]),
                    ]),
            ])
            ->statePath('data');
    }

    public function save(
        SettingsRepository $settings,
        ThemeManager $themes,
        CmsCacheVersions $versions,
    ): void {
        $data = $this->form->getState();

        // Snapshot current permalink settings (for cache bump decision)
        $currentPermalinkMode = (string) $settings->get('core', 'permalink_mode', 'post_name');
        $currentPermalinkCustom = (string) $settings->get('core', 'permalink_custom_structure', '/%postname%');
        $currentCategoryBase = (string) $settings->get('core', 'category_base', 'category');
        $currentTagBase = (string) $settings->get('core', 'tag_base', 'tag');

        // Snapshot current attachment settings (for cache bump decision)
        $currentAttachmentsEnabled = (bool) $settings->get('core', 'attachment_pages_enabled', false);
        $currentAttachmentsIndexable = (bool) $settings->get('core', 'attachment_pages_indexable', true);

        // ✅ Snapshot current SEO search engine block (for cache bump decision)
        $currentSearchEngineBlock = (bool) $settings->get('seo', 'search_engine_block', false);

        // ✅ Snapshot current site_url for cache bump decision
        $currentSiteUrl = $this->normalizeSiteUrl(
            (string) $settings->get('core', 'site_url', rtrim((string) config('app.url'), '/'))
        );

        // ✅ Snapshot current status (header/topbar uses it)
        $currentStatus = (string) $settings->get('core', 'status', '');

        // ✅ Normalize site url (WP-like, dev-safe)
        $siteUrl = $this->normalizeSiteUrl((string) ($data['site_url'] ?? ''));

        // Core
        $settings->set('core', 'site_name', (string) ($data['site_name'] ?? ''));
        $settings->set('core', 'site_url', $siteUrl);
        $settings->set('core', 'timezone', (string) ($data['timezone'] ?? ''));

        // Contact
        $status = (string) ($data['status'] ?? '');
        $settings->set('core', 'status', $status);
        $settings->set('core', 'contact_phone', (string) ($data['contact_phone'] ?? ''));
        $settings->set('core', 'contact_email', (string) ($data['contact_email'] ?? ''));

        // Homepage
        $homepageId = $data['homepage_page_id'] ?? null;
        $homepageId = is_numeric($homepageId) ? (int) $homepageId : null;
        if ($homepageId !== null && $homepageId <= 0) {
            $homepageId = null;
        }
        $settings->set('core', 'homepage_page_id', $homepageId);

        // ✅ SEO (WP-like)
        $searchEngineBlock = (bool) ($data['search_engine_block'] ?? false);
        $settings->set('seo', 'search_engine_block', $searchEngineBlock);

        // ✅ Attachment pages (global)
        $attachmentsEnabled = (bool) ($data['attachment_pages_enabled'] ?? false);
        $attachmentsIndexable = (bool) ($data['attachment_pages_indexable'] ?? true);
        $settings->set('core', 'attachment_pages_enabled', $attachmentsEnabled);
        $settings->set('core', 'attachment_pages_indexable', $attachmentsIndexable);

        // ✅ Permalinks (normalize)
        $permalinkMode = (string) ($data['permalink_mode'] ?? 'post_name');
        $customStructure = trim((string) ($data['permalink_custom_structure'] ?? '/%postname%'));
        if ($customStructure === '') {
            $customStructure = '/%postname%';
        }
        if (!str_starts_with($customStructure, '/')) {
            $customStructure = '/' . $customStructure;
        }

        $categoryBase = trim((string) ($data['category_base'] ?? 'category'));
        $tagBase = trim((string) ($data['tag_base'] ?? 'tag'));
        if ($categoryBase === '') {
            $categoryBase = 'category';
        }
        if ($tagBase === '') {
            $tagBase = 'tag';
        }

        $settings->set('core', 'permalink_mode', $permalinkMode);
        $settings->set('core', 'permalink_custom_structure', $customStructure);
        $settings->set('core', 'category_base', $categoryBase);
        $settings->set('core', 'tag_base', $tagBase);

        $renderChanged = false;
        $errors = [];

        // ✅ If site_url changed, bump render cache (sitemap/canonicals/menus may include full URLs)
        if (rtrim($siteUrl, '/') !== rtrim($currentSiteUrl, '/')) {
            $renderChanged = true;
        }

        // ✅ If status changed, bump render cache (header/topbar/footer may include it)
        if ($status !== $currentStatus) {
            $renderChanged = true;
        }

        // ✅ If SEO block changed, bump render cache (robots meta + sitemap behavior + canonicals)
        if ($searchEngineBlock !== $currentSearchEngineBlock) {
            $renderChanged = true;
        }

        // If permalink settings changed, bump render cache (menus/SEO/canonicals)
        if (
            $permalinkMode !== $currentPermalinkMode ||
            $customStructure !== $currentPermalinkCustom ||
            $categoryBase !== $currentCategoryBase ||
            $tagBase !== $currentTagBase
        ) {
            $renderChanged = true;
        }

        // If attachment settings changed, bump render cache (SEO/sitemap/canonicals)
        if (
            $attachmentsEnabled !== $currentAttachmentsEnabled ||
            $attachmentsIndexable !== $currentAttachmentsIndexable
        ) {
            $renderChanged = true;
        }

        // Theme change
        $currentTheme = $themes->activeSlug();
        $newTheme = (string) ($data['active_theme'] ?? $currentTheme);

        if ($newTheme !== '' && $newTheme !== $currentTheme) {
            try {
                $themes->activate($newTheme);
                $renderChanged = true;
            } catch (Throwable $e) {
                $errors[] = "Theme activation ({$newTheme}): " . $e->getMessage();
            }
        }

        if ($renderChanged) {
            $versions->bumpRender();
            $themes->forgetDiscoveryCache();
        }

        if ($errors !== []) {
            Notification::make()
                ->danger()
                ->title('Saved with warnings')
                ->body(implode("\n", $errors))
                ->send();
            return;
        }

        Notification::make()
            ->success()
            ->title('Saved')
            ->send();

        // Refresh form state (keep UI consistent)
        $this->form->fill([
            ...$data,
            'site_url' => $siteUrl, // ✅ show normalized value back to admin
            'active_theme' => $themes->activeSlug(),
            'permalink_custom_structure' => $customStructure,
            'category_base' => $categoryBase,
            'tag_base' => $tagBase,
            'attachment_pages_enabled' => $attachmentsEnabled,
            'attachment_pages_indexable' => $attachmentsIndexable,
            'search_engine_block' => $searchEngineBlock,
        ]);
    }
}