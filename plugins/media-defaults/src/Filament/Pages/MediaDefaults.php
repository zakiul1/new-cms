<?php

namespace Plugins\MediaDefaults\Filament\Pages;

use App\Cms\Core\Settings;
use App\Filament\Forms\Components\WpClassicEditor;
use App\Models\Media;
use App\Models\Taxonomy;
use App\Models\Term;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Support\Collection;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use Filament\Schemas\Components\Actions;



use UnitEnum;

class MediaDefaults extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string|UnitEnum|null $navigationGroup = 'Media';
    protected static ?string $navigationLabel = 'Media Defaults';
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-adjustments-horizontal';
    protected static ?int $navigationSort = 60;

    protected string $view = 'media-defaults::filament.pages.media-defaults';

    /**
     * Selected media category term id (PUBLIC only).
     */
    public ?int $activeCategoryId = null;

    /**
     * Options for select: [id => name]
     */
    public array $categoryOptions = [];

    /**
     * Preview media selection (any public attachment with slug)
     */
    public ?int $previewMediaId = null;

    /**
     * Cache-busting for iframe reload
     */
    public string $previewNonce = '';

    /**
     * Form data for defaults.
     */
    public array $data = [
        'default_title' => '',
        'default_description' => '',
        'default_sub_title' => '',
        'default_sub_description' => '',

        // optional plugin-level (if you want)
        'default_assets_css' => '',
        'default_assets_js' => '',
    ];

    protected function getForms(): array
    {
        return ['form'];
    }

    public function mount(): void
    {
        $this->previewNonce = Str::random(10);

        $this->categoryOptions = $this->loadPublicMediaCategoryOptions();

        // Default to first category if none selected
        if ($this->activeCategoryId === null && !empty($this->categoryOptions)) {
            $firstId = array_key_first($this->categoryOptions);
            $this->activeCategoryId = $firstId !== null ? (int) $firstId : null;
        }

        // ✅ pick default preview media FROM selected category
        $this->previewMediaId = $this->pickDefaultPreviewMediaId();

        $this->loadDefaultsIntoForm();

        // seed preview session for iframe
        $this->syncPreviewSession();
    }

    /**
     * Returns [id => name] for PUBLIC media categories.
     */
    protected function loadPublicMediaCategoryOptions(): array
    {
        $taxonomyId = Taxonomy::idByKey('media_category');
        if (!$taxonomyId) {
            return [];
        }

        /** @var Collection<int, Term> $terms */
        $terms = Term::query()
            ->where('taxonomy_id', $taxonomyId)
            ->where('visibility', 'public')
            ->orderBy('name')
            ->get(['id', 'name']);

        return $terms->pluck('name', 'id')->all();
    }

    /**
     * ✅ Pick the latest media (public + has slug) from the selected category.
     */
    protected function pickDefaultPreviewMediaId(): ?int
    {
        $taxonomyId = Taxonomy::idByKey('media_category');
        if (!$taxonomyId || !$this->activeCategoryId) {
            // fallback: any public media with slug
            return Media::query()
                ->whereNotNull('slug')
                ->where('attachment_public', true)
                ->latest('id')
                ->value('id');
        }

        return Media::query()
            ->whereNotNull('slug')
            ->where('attachment_public', true)
            ->whereHas('terms', function ($q) use ($taxonomyId) {
                $q->where('terms.taxonomy_id', $taxonomyId)
                    ->where('terms.visibility', 'public')
                    ->where('terms.id', $this->activeCategoryId);
            })
            ->latest('id')
            ->value('id');
    }

    /**
     * Load defaults for selected category (category-wise) with global fallback.
     */
    protected function loadDefaultsIntoForm(): void
    {
        $settings = app(Settings::class);
        $group = 'plugins.media-defaults';

        // Global fallback
        $global = [
            'default_title' => (string) $settings->get('default_title', '', $group),
            'default_description' => (string) $settings->get('default_description', '', $group),
            'default_sub_title' => (string) $settings->get('default_sub_title', '', $group),
            'default_sub_description' => (string) $settings->get('default_sub_description', '', $group),

            // optional plugin-level
            'default_assets_css' => (string) $settings->get('default_assets_css', '', $group),
            'default_assets_js' => (string) $settings->get('default_assets_js', '', $group),
        ];

        $categoryDefaults = (array) $settings->get('category_defaults', [], $group);

        $cat = [];
        if ($this->activeCategoryId !== null) {
            $cat = $categoryDefaults[(string) $this->activeCategoryId] ?? [];
            $cat = is_array($cat) ? $cat : [];
        }

        $this->data = [
            'default_title' => (string) ($cat['default_title'] ?? $global['default_title']),
            'default_description' => (string) ($cat['default_description'] ?? $global['default_description']),
            'default_sub_title' => (string) ($cat['default_sub_title'] ?? $global['default_sub_title']),
            'default_sub_description' => (string) ($cat['default_sub_description'] ?? $global['default_sub_description']),

            // optional plugin-level
            'default_assets_css' => (string) ($global['default_assets_css'] ?? ''),
            'default_assets_js' => (string) ($global['default_assets_js'] ?? ''),
        ];

        $this->form->fill([
            'activeCategoryId' => $this->activeCategoryId,
            'previewMediaId' => $this->previewMediaId,
            'data' => $this->data,
        ]);

        $this->bustPreview(); // reload iframe
    }

    /**
     * Called when dropdown changes (Livewire).
     */
    public function updatedActiveCategoryId($value): void
    {
        $termId = is_numeric($value) ? (int) $value : null;

        if ($termId !== null && !array_key_exists($termId, $this->categoryOptions)) {
            Notification::make()->title('Invalid category')->danger()->send();
            return;
        }

        $this->activeCategoryId = $termId;

        // ✅ reset preview media to latest item from this category
        $this->previewMediaId = $this->pickDefaultPreviewMediaId();

        $this->loadDefaultsIntoForm();
    }

    public function updatedPreviewMediaId($value): void
    {
        $id = is_numeric($value) ? (int) $value : null;
        $this->previewMediaId = $id;
        $this->bustPreview();
    }

    /**
     * Save current form state into session so frontend attachment can render it.
     */
    protected function syncPreviewSession(): void
    {
        $state = $this->form->getState();
        if (!is_array($state)) {
            $state = [];
        }

        $payload = [
            'activeCategoryId' => $this->activeCategoryId,
            'data' => is_array($state['data'] ?? null) ? $state['data'] : $this->data,
        ];

        session()->put('media_defaults_preview_state', $payload);
    }

    /**
     * Cache-bust iframe + sync session
     */
    protected function bustPreview(): void
    {
        $this->syncPreviewSession();
        $this->previewNonce = Str::random(10);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->columns([
                'default' => 1,
                'lg' => 3,
            ])
            ->schema([
                // LEFT: Tabs (2 columns)
                Tabs::make('MediaDefaultsTabs')
                    ->columnSpan([
                        'default' => 1,
                        'lg' => 2,
                    ])
                    ->tabs([
                        Tab::make('Content')
                            ->schema([
                                Section::make('Media Defaults')
                                    ->description('These defaults are applied when you open Edit Media. Only empty fields are auto-filled.')
                                    ->statePath('data')
                                    ->schema([
                                        TextInput::make('default_title')
                                            ->label('Default Title')
                                            ->maxLength(255)
                                            ->helperText('Used only if Media title is empty.')
                                            ->live(onBlur: true)
                                            ->afterStateUpdated(fn() => $this->bustPreview())
                                            ->columnSpanFull(),

                                        WpClassicEditor::make('default_description')
                                            ->label('Default Description (Product)')
                                            ->height(260)
                                            ->helperText('Used only if Media description is empty.')
                                            ->live(onBlur: true)
                                            ->afterStateUpdated(fn() => $this->bustPreview())
                                            ->columnSpanFull(),

                                        TextInput::make('default_sub_title')
                                            ->label('Default Sub title')
                                            ->maxLength(255)
                                            ->helperText('Used only if Media Sub title is empty.')
                                            ->live(onBlur: true)
                                            ->afterStateUpdated(fn() => $this->bustPreview())
                                            ->columnSpanFull(),

                                        WpClassicEditor::make('default_sub_description')
                                            ->label('Default Sub description')
                                            ->height(260)
                                            ->helperText('Used only if Media Sub description is empty.')
                                            ->live(onBlur: true)
                                            ->afterStateUpdated(fn() => $this->bustPreview())
                                            ->columnSpanFull(),
                                    ]),


                                Actions::make([
                                    Action::make('saveBottom')
                                        ->label('Save Data')
                                        ->icon('heroicon-o-check')
                                        ->color('primary')
                                        ->action(fn() => $this->save()),
                                ])
                                    ->alignment('right')
                                    ->columnSpanFull(),

                            ]),

                        Tab::make('Custom CSS & JS')
                            ->schema([
                                Section::make('Custom CSS & JS')
                                    ->description('Optional: these are for preview use unless you also apply them on frontend.')
                                    ->statePath('data')
                                    ->schema([
                                        Textarea::make('default_assets_css')
                                            ->label('Default CSS')
                                            ->rows(10)
                                            ->live(onBlur: true)
                                            ->afterStateUpdated(fn() => $this->bustPreview())
                                            ->columnSpanFull(),

                                        Textarea::make('default_assets_js')
                                            ->label('Default JS')
                                            ->rows(10)
                                            ->live(onBlur: true)
                                            ->afterStateUpdated(fn() => $this->bustPreview())
                                            ->columnSpanFull(),
                                    ]),


                            ]),

                        Tab::make('Frontend Preview')
                            ->schema([
                                Section::make('Preview')
                                    ->schema([
                                        /**
                                         * ✅ Fix: with getSearchResultsUsing(), Filament will NOT show a default list
                                         * until you type something. So we provide:
                                         * - options() => default list (latest 100 from selected category)
                                         * - getSearchResultsUsing() => search results
                                         */
                                        Select::make('previewMediaId')
                                            ->label('Preview media')
                                            ->helperText('Shows only media from the selected category. Use search to find quickly.')
                                            ->native(false)
                                            ->searchable()
                                            ->searchPrompt('Search media...')
                                            ->noSearchResultsMessage('No media found for this category/search.')

                                            // ✅ Default list when you open dropdown (before typing)
                                            ->options(function (): array {
                                                $taxonomyId = Taxonomy::idByKey('media_category');

                                                return Media::query()
                                                    ->whereNotNull('slug')
                                                    ->where('attachment_public', true)
                                                    ->when($taxonomyId && $this->activeCategoryId, function ($qq) use ($taxonomyId) {
                                                        $qq->whereHas('terms', function ($t) use ($taxonomyId) {
                                                            $t->where('terms.taxonomy_id', $taxonomyId)
                                                                ->where('terms.visibility', 'public')
                                                                ->where('terms.id', $this->activeCategoryId);
                                                        });
                                                    })
                                                    ->latest('id')
                                                    ->limit(100)
                                                    ->get(['id', 'slug', 'original_filename', 'title'])
                                                    ->mapWithKeys(function (Media $m) {
                                                        $label = trim((string) ($m->title ?: $m->original_filename ?: $m->slug));
                                                        $slug = (string) $m->slug;

                                                        return [
                                                            $m->id => Str::limit($label, 60) . '  (/' . $slug . ')',
                                                        ];
                                                    })
                                                    ->all();
                                            })

                                            // ✅ Search results when typing
                                            ->getSearchResultsUsing(function (string $search): array {
                                                $taxonomyId = Taxonomy::idByKey('media_category');

                                                $q = Media::query()
                                                    ->whereNotNull('slug')
                                                    ->where('attachment_public', true)
                                                    ->when($taxonomyId && $this->activeCategoryId, function ($qq) use ($taxonomyId) {
                                                        $qq->whereHas('terms', function ($t) use ($taxonomyId) {
                                                            $t->where('terms.taxonomy_id', $taxonomyId)
                                                                ->where('terms.visibility', 'public')
                                                                ->where('terms.id', $this->activeCategoryId);
                                                        });
                                                    });

                                                $search = trim($search);

                                                // ✅ If empty search, still return a list (so dropdown doesn't look empty)
                                                if ($search !== '') {
                                                    $q->where(function ($w) use ($search) {
                                                        $w->where('title', 'like', '%' . $search . '%')
                                                            ->orWhere('original_filename', 'like', '%' . $search . '%')
                                                            ->orWhere('slug', 'like', '%' . $search . '%');
                                                    });
                                                }

                                                return $q->latest('id')
                                                    ->limit(100)
                                                    ->get(['id', 'slug', 'original_filename', 'title'])
                                                    ->mapWithKeys(function (Media $m) {
                                                        $label = trim((string) ($m->title ?: $m->original_filename ?: $m->slug));
                                                        $slug = (string) $m->slug;

                                                        return [
                                                            $m->id => Str::limit($label, 60) . '  (/' . $slug . ')',
                                                        ];
                                                    })
                                                    ->all();
                                            })

                                            // ✅ Keep the selected label nice (for already-selected value)
                                            ->getOptionLabelUsing(function ($value): ?string {
                                                $id = is_numeric($value) ? (int) $value : null;
                                                if (!$id) {
                                                    return null;
                                                }

                                                $m = Media::query()->find($id);
                                                if (!$m) {
                                                    return null;
                                                }

                                                $label = trim((string) ($m->title ?: $m->original_filename ?: $m->slug));
                                                return Str::limit($label, 80) . '  (/' . (string) $m->slug . ')';
                                            })

                                            ->live()
                                            ->afterStateUpdated(fn() => $this->bustPreview())
                                            ->columnSpanFull(),

                                        Placeholder::make('frontend_preview')
                                            ->label('')
                                            ->content(function (Get $get): HtmlString {
                                                $mediaId = $get('previewMediaId');
                                                $mediaId = is_numeric($mediaId) ? (int) $mediaId : null;

                                                if (!$mediaId) {
                                                    return new HtmlString('<div class="text-sm text-slate-500">Select a media item to preview.</div>');
                                                }

                                                /** @var Media|null $media */
                                                $media = Media::query()->find($mediaId);

                                                if (!$media || !filled($media->slug)) {
                                                    return new HtmlString('<div class="text-sm text-slate-500">Preview media not found or missing slug.</div>');
                                                }

                                                $url = url('/' . ltrim((string) $media->slug, '/'))
                                                    . '?md_preview=1&_ts=' . urlencode($this->previewNonce);

                                                return new HtmlString(
                                                    '<iframe src="' . e($url) . '" class="w-full rounded-xl border" style="height: 70vh;"></iframe>'
                                                );
                                            })
                                            ->dehydrated(false),
                                    ]),
                            ]),

                    ]),

                // RIGHT: Category select (1 column)
                Section::make('Media Category')
                    ->description('Select a category to set its defaults.')
                    ->columnSpan([
                        'default' => 1,
                        'lg' => 1,
                    ])
                    ->extraAttributes([
                        'class' => 'lg:sticky lg:top-6',
                    ])
                    ->schema([
                        Placeholder::make('active_label')
                            ->label('Active')
                            ->content(function (): string {
                                if ($this->activeCategoryId === null) {
                                    return '—';
                                }

                                return (string) ($this->categoryOptions[$this->activeCategoryId] ?? '—');
                            }),

                        Select::make('activeCategoryId')
                            ->label('Choose category')
                            ->options(fn() => $this->categoryOptions)
                            ->searchable()
                            ->native(false)
                            ->placeholder('Select category...')
                            ->required()
                            ->live(),
                    ]),
            ]);
    }

    protected function getHeaderActions(): array
    {
        return [


            Action::make('refreshPreview')
                ->label('Refresh preview')
                ->icon('heroicon-o-arrow-path')
                ->color('gray')
                ->action(fn() => $this->bustPreview()),
            Action::make('save')
                ->label('Save Data')
                ->icon('heroicon-o-check')
                ->color('primary')
                ->action(fn() => $this->save()),
        ];
    }

    public function save(): void
    {
        $settings = app(Settings::class);
        $group = 'plugins.media-defaults';

        $state = $this->form->getState();

        if (is_array($state) && isset($state['data']) && is_array($state['data'])) {
            $this->data = $state['data'];
        }

        if (is_array($state) && array_key_exists('activeCategoryId', $state)) {
            $val = $state['activeCategoryId'];
            $this->activeCategoryId = is_numeric($val) ? (int) $val : null;
        }

        if ($this->activeCategoryId === null) {
            Notification::make()->title('Please select a category')->danger()->send();
            return;
        }

        $categoryDefaults = (array) $settings->get('category_defaults', [], $group);
        if (!is_array($categoryDefaults)) {
            $categoryDefaults = [];
        }

        $categoryDefaults[(string) $this->activeCategoryId] = [
            'default_title' => (string) ($this->data['default_title'] ?? ''),
            'default_description' => (string) ($this->data['default_description'] ?? ''),
            'default_sub_title' => (string) ($this->data['default_sub_title'] ?? ''),
            'default_sub_description' => (string) ($this->data['default_sub_description'] ?? ''),
        ];

        $settings->set('category_defaults', $categoryDefaults, $group);

        // optional plugin-level css/js save (only if you want to persist)
        $settings->set('default_assets_css', (string) ($this->data['default_assets_css'] ?? ''), $group);
        $settings->set('default_assets_js', (string) ($this->data['default_assets_js'] ?? ''), $group);

        $this->syncPreviewSession();
        $this->bustPreview();

        Notification::make()->title('Saved')->success()->send();
    }

    protected function getViewData(): array
    {
        return [
            'categoryOptions' => $this->categoryOptions,
        ];
    }
}