<?php

namespace App\Filament\Resources\MediaResource\Pages;

use App\Cms\Core\SettingsRepository;
use App\Cms\Media\MediaUploader;
use App\Filament\Forms\Components\WpClassicEditor;
use App\Filament\Resources\MediaResource;
use App\Jobs\GenerateMediaVariants;
use App\Models\Media;
use App\Models\Taxonomy;
use App\Models\Term;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class EditMedia extends EditRecord
{
    protected static string $resource = MediaResource::class;

    /** @var TemporaryUploadedFile|UploadedFile|null */
    protected TemporaryUploadedFile|UploadedFile|null $pendingReplaceFile = null;

    /** @var array<int> */
    protected array $pendingCategoryTermIds = [];

    public function getMaxContentWidth(): Width
    {
        return Width::Full;
    }

    public function mount(int|string $record): void
    {
        parent::mount($record);

        /** @var Media $media */
        $media = $this->record;

        // ✅ normalize legacy slugs once, so regex validation won't fail on untouched save
        if (filled($media->slug)) {
            $fixed = Str::slug((string) $media->slug);

            if ($fixed !== $media->slug) {
                $media->slug = $fixed;
                $media->saveQuietly();
                $this->fillForm();
            }
        }
    }

    // ------------------------------------------------------------------
    // Helpers (keep: used by your editors + private category logic)
    // ------------------------------------------------------------------

    protected function editorValueToString($value): string
    {
        if ($value === null) {
            return '';
        }

        if (is_string($value)) {
            return $value;
        }

        if (is_array($value)) {
            return (string) ($value['html'] ?? $value['value'] ?? '');
        }

        if (is_object($value)) {
            $arr = (array) $value;
            return (string) ($arr['html'] ?? $arr['value'] ?? '');
        }

        return (string) $value;
    }

    protected function isFrontendBlockedByPrivateCategory(Media $record): bool
    {
        // If Media has categories() relation, best:
        if (method_exists($record, 'categories')) {
            return $record->categories()
                ->where('terms.visibility', 'private')
                ->exists();
        }

        // Fallback: use terms() with media_category taxonomy
        $taxonomyId = Taxonomy::query()->where('key', 'media_category')->value('id');
        if (!$taxonomyId || !method_exists($record, 'terms')) {
            return false;
        }

        return $record->terms()
            ->where('terms.taxonomy_id', $taxonomyId)
            ->where('terms.visibility', 'private')
            ->exists();
    }

    // ------------------------------------------------------------------
    // ✅ Product field helpers (from selected media category term)
    // ------------------------------------------------------------------

    protected function guessProductFromSelectedCategory(array $categoryIds): string
    {
        $categoryIds = array_values(array_filter(array_map('intval', $categoryIds), fn($id) => $id > 0));

        if (empty($categoryIds)) {
            return '';
        }

        $taxonomyId = Taxonomy::query()->where('key', 'media_category')->value('id');
        if (!$taxonomyId) {
            return '';
        }

        $term = Term::query()
            ->where('taxonomy_id', $taxonomyId)
            ->whereIn('id', $categoryIds)
            ->orderBy('name')
            ->first();

        // Term must have a `product` column for this to work.
        return $term ? trim((string) ($term->product ?? '')) : '';
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->columns([
                'default' => 1,
                'lg' => 3,
            ])
            ->components([
                Tabs::make('Editor')
                    ->columnSpan([
                        'default' => 1,
                        'lg' => 2,
                    ])
                    ->tabs([
                        Tab::make('Content')
                            ->schema([
                                TextInput::make('title')
                                    ->label('H1  [h1] for shortcode')
                                    ->required()
                                    ->maxLength(255)
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(function ($state, Set $set, Get $get) {
                                        if (!filled($get('slug'))) {
                                            $set('slug', Str::slug((string) $state));
                                        }

                                        if (!filled($get('meta.seo.title'))) {
                                            $set('meta.seo.title', (string) $state);
                                        }

                                        if (!filled($get('meta.frontend.meta_title'))) {
                                            $set('meta.frontend.meta_title', (string) $state);
                                        }
                                    }),

                                // ✅ Product (auto from selected category's product; user can override)
                                TextInput::make('meta.frontend.product')
                                    ->label('Product [category] for shortcode')
                                    ->maxLength(255)
                                    ->helperText('Defaults from selected category Product. You can override per media.')
                                    ->formatStateUsing(fn($state) => is_string($state) ? $state : '')
                                    ->afterStateHydrated(function (Set $set, Get $get) {
                                        $current = trim((string) $get('meta.frontend.product'));
                                        if ($current !== '') {
                                            return;
                                        }

                                        $ids = $get('category_term_ids');
                                        $ids = is_array($ids) ? $ids : [];

                                        // fallback to record terms if form hasn't hydrated category yet
                                        if (empty($ids) && $this->record) {
                                            $taxonomyId = Taxonomy::query()->where('key', 'media_category')->value('id');
                                            if ($taxonomyId) {
                                                $ids = $this->record->terms()
                                                    ->where('terms.taxonomy_id', $taxonomyId)
                                                    ->pluck('terms.id')
                                                    ->map(fn($id) => (int) $id)
                                                    ->all();
                                            }
                                        }

                                        $product = $this->guessProductFromSelectedCategory($ids);

                                        if ($product !== '') {
                                            $set('meta.frontend.product', $product);
                                        }
                                    })
                                    ->live(onBlur: true),

                                TextInput::make('slug')
                                    ->label('Slug (optional)')
                                    ->maxLength(255)
                                    ->regex('/^[a-z0-9]+(?:-[a-z0-9]+)*$/')
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(function ($state, Set $set) {
                                        $set('slug', filled($state) ? Str::slug((string) $state) : null);
                                    })
                                    ->dehydrateStateUsing(fn($state) => filled($state) ? Str::slug((string) $state) : null)
                                    ->helperText('Leave blank to auto-generate. Controls /{slug}'),

                                Placeholder::make('permalink_preview')
                                    ->label('Permalink')
                                    ->content(function (?Media $record, Get $get) {
                                        $base = rtrim((string) config('app.url'), '/');

                                        $slug = $record?->slug ?: trim((string) $get('slug'), '/');
                                        if ($slug === '') {
                                            $slug = Str::slug((string) ($get('title') ?? ''));
                                        }
                                        $slug = $slug !== '' ? $slug : '(auto)';

                                        return $base . '/' . ltrim($slug, '/');
                                    }),

                                WpClassicEditor::make('description')
                                    ->label('Description (Product)')
                                    ->height(260)
                                    ->toolbar([
                                        'blocks',
                                        'bold italic underline',
                                        'bullist numlist',
                                        'link blockquote',
                                        'removeformat',
                                    ])
                                    ->formatStateUsing(function ($state) {
                                        if (is_string($state)) {
                                            return $state;
                                        }
                                        if (is_array($state)) {
                                            return (string) ($state['html'] ?? $state['value'] ?? '');
                                        }
                                        return '';
                                    })
                                    ->dehydrateStateUsing(function ($state) {
                                        if (is_string($state)) {
                                            return $state;
                                        }
                                        if (is_array($state)) {
                                            return (string) ($state['html'] ?? $state['value'] ?? '');
                                        }
                                        return '';
                                    })
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(function ($state, Set $set, Get $get) {
                                        $current = data_get($get('meta') ?? [], 'frontend.meta_description');

                                        $currentString = is_string($current)
                                            ? $current
                                            : (is_array($current) ? (string) ($current['html'] ?? $current['value'] ?? '') : '');

                                        if (!filled($currentString)) {
                                            $set('meta.frontend.meta_description', (string) $state);
                                        }

                                        if (!filled($get('meta.seo.description'))) {
                                            $plain = trim(strip_tags((string) $state));
                                            if ($plain !== '') {
                                                $set('meta.seo.description', Str::limit($plain, 160, ''));
                                            }
                                        }
                                    }),

                                TextInput::make('meta.frontend.meta_title')
                                    ->label('Sub title')
                                    ->helperText('Used on attachment page (frontend) under related section.')
                                    ->maxLength(255)
                                    ->live(onBlur: true),

                                WpClassicEditor::make('meta.frontend.meta_description')
                                    ->label('Sub description')
                                    ->helperText('Used on attachment page (frontend) under related section.')
                                    ->height(220)
                                    ->toolbar([
                                        'blocks',
                                        'bold italic underline',
                                        'bullist numlist',
                                        'link blockquote',
                                        'removeformat',
                                    ])
                                    ->formatStateUsing(function ($state) {
                                        if (is_string($state)) {
                                            return $state;
                                        }
                                        if (is_array($state)) {
                                            return (string) ($state['html'] ?? $state['value'] ?? '');
                                        }
                                        return '';
                                    })
                                    ->dehydrateStateUsing(function ($state) {
                                        if (is_string($state)) {
                                            return $state;
                                        }
                                        if (is_array($state)) {
                                            return (string) ($state['html'] ?? $state['value'] ?? '');
                                        }
                                        return '';
                                    })
                                    ->live(onBlur: true),

                                Section::make('SEO (Premium)')
                                    ->description('Control how this attachment page appears in Google and when shared on social media.')
                                    ->collapsible()
                                    ->collapsed()
                                    ->schema([
                                        TextInput::make('meta.seo.title')
                                            ->label('SEO Title')
                                            ->helperText('')
                                            ->maxLength(1140)
                                            ->live(onBlur: true),

                                        Textarea::make('meta.seo.description')
                                            ->label('Meta Description')
                                            ->helperText('')
                                            ->rows(3)
                                            ->maxLength(2000)
                                            ->live(onBlur: true),

                                        TextInput::make('meta.seo.canonical')
                                            ->label('Canonical URL (optional)')
                                            ->placeholder('https://example.com/your-page')
                                            ->helperText('Leave empty to auto-use the current URL.')
                                            ->maxLength(255),

                                        Select::make('meta.seo.robots')
                                            ->label('Robots')
                                            ->helperText('Default: index, follow')
                                            ->options([
                                                '' => 'Default (index, follow)',
                                                'index, follow' => 'index, follow',
                                                'noindex, follow' => 'noindex, follow',
                                                'index, nofollow' => 'index, nofollow',
                                                'noindex, nofollow' => 'noindex, nofollow',
                                            ])
                                            ->default(''),

                                        TextInput::make('meta.seo.og_image')
                                            ->label('OpenGraph Image (optional)')
                                            ->helperText('Absolute URL or path. Used for Facebook/Twitter previews.')
                                            ->maxLength(255),
                                    ])
                                    ->columnSpanFull(),
                            ]),

                        Tab::make('Custom CSS & JS')
                            ->schema([
                                Textarea::make('meta.assets.css')
                                    ->label('Custom CSS')
                                    ->helperText('Applies to this attachment page only. Output inside <head>.')
                                    ->rows(14)
                                    ->extraAttributes([
                                        'style' => 'font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;',
                                    ])
                                    ->live(onBlur: true),

                                Textarea::make('meta.assets.js')
                                    ->label('Custom JS')
                                    ->helperText('Applies to this attachment page only. Output before </body>.')
                                    ->rows(14)
                                    ->extraAttributes([
                                        'style' => 'font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;',
                                    ])
                                    ->live(onBlur: true),

                                Textarea::make('meta.custom_json')
                                    ->label('Custom JSON (WP-like)')
                                    ->helperText('Valid JSON only. Saved per media record. (Do not include <script> tag)')
                                    ->rows(18)
                                    ->nullable()
                                    ->rules(['json'])
                                    ->formatStateUsing(function ($state) {
                                        if (blank($state)) {
                                            return '';
                                        }

                                        if (is_array($state)) {
                                            return json_encode(
                                                $state,
                                                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
                                            ) ?: '';
                                        }

                                        return (string) $state;
                                    })
                                    ->dehydrateStateUsing(function ($state) {
                                        $state = trim((string) $state);

                                        if ($state === '') {
                                            return null;
                                        }

                                        $decoded = json_decode($state, true);

                                        if (json_last_error() !== JSON_ERROR_NONE) {
                                            return null;
                                        }

                                        return $decoded;
                                    })
                                    ->live(onBlur: true),
                            ]),

                        Tab::make('Frontend Preview')
                            ->schema([
                                Placeholder::make('frontend_preview')
                                    ->label('')
                                    ->content(function (): \Illuminate\Support\HtmlString {
                                        /** @var \App\Models\Media|null $record */
                                        $record = $this->record;

                                        if (!$record) {
                                            return new \Illuminate\Support\HtmlString('');
                                        }

                                        $url = url('/' . ltrim($record->slug, '/'));

                                        return new \Illuminate\Support\HtmlString(
                                            '<iframe src="' . e($url) . '" class="w-full rounded-xl border" style="height: 70vh;"></iframe>'
                                        );
                                    })
                                    ->dehydrated(false),
                            ]),
                    ]),

                Section::make('Media')
                    ->columnSpan([
                        'default' => 1,
                        'lg' => 1,
                    ])
                    ->schema([
                        Placeholder::make('right_preview')
                            ->label('Preview')
                            ->content(function (): HtmlString {
                                /** @var Media|null $record */
                                $record = $this->record;

                                if (!$record) {
                                    return new HtmlString('');
                                }

                                if ($record->isImage()) {
                                    $src = e($record->url());

                                    return new HtmlString(
                                        '<div class="rounded-lg border bg-white p-3">
                                            <img src="' . $src . '" alt="" class="w-full rounded-md object-contain" />
                                        </div>'
                                    );
                                }

                                return new HtmlString(
                                    '<div class="rounded-lg border bg-white p-3 text-sm text-slate-600">
                                        File: ' . e((string) $record->original_filename) . '
                                    </div>'
                                );
                            })
                            ->dehydrated(false)
                            ->columnSpanFull(),

                        FileUpload::make('replace_file')
                            ->label('Replace file')
                            ->storeFiles(false)
                            ->helperText('Replaces original file. Variants regenerate for images.'),

                        Select::make('category_term_ids')
                            ->label('Categories')
                            ->helperText('Assign categories to this media. You can create new categories here.')
                            ->multiple()
                            ->searchable()
                            ->preload()
                            ->nullable()
                            ->options(function (): array {
                                $taxonomyId = Taxonomy::firstOrCreate(
                                    ['key' => 'media_category'],
                                    ['label' => 'Media Categories', 'hierarchical' => true],
                                )->id;

                                return Term::query()
                                    ->where('taxonomy_id', $taxonomyId)
                                    ->orderBy('name')
                                    ->pluck('name', 'id')
                                    ->all();
                            })
                            ->afterStateHydrated(function ($state, Set $set) {
                                /** @var Media $record */
                                $record = $this->record;

                                $taxonomyId = Taxonomy::query()->where('key', 'media_category')->value('id');

                                $ids = $taxonomyId
                                    ? $record->terms()
                                        ->where('terms.taxonomy_id', $taxonomyId)
                                        ->pluck('terms.id')
                                        ->all()
                                    : [];

                                $set('category_term_ids', $ids);
                            })
                            // ✅ when categories change, auto-fill Product if empty
                            ->afterStateUpdated(function ($state, Set $set, Get $get) {
                                $current = trim((string) $get('meta.frontend.product'));
                                if ($current !== '') {
                                    return;
                                }

                                $ids = is_array($state) ? $state : [];
                                $product = $this->guessProductFromSelectedCategory($ids);

                                if ($product !== '') {
                                    $set('meta.frontend.product', $product);
                                }
                            })
                            ->createOptionForm([
                                TextInput::make('name')
                                    ->required()
                                    ->maxLength(255)
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(function ($state, callable $set) {
                                        $set('slug', Str::slug((string) $state));
                                    }),

                                TextInput::make('slug')
                                    ->label('Slug (optional)')
                                    ->maxLength(255),

                                Select::make('parent_id')
                                    ->label('Parent Category (optional)')
                                    ->searchable()
                                    ->preload()
                                    ->nullable()
                                    ->options(function (): array {
                                        $taxonomyId = Taxonomy::where('key', 'media_category')->value('id');
                                        if (!$taxonomyId) {
                                            return [];
                                        }

                                        return Term::query()
                                            ->where('taxonomy_id', $taxonomyId)
                                            ->orderBy('name')
                                            ->pluck('name', 'id')
                                            ->all();
                                    }),
                            ])
                            ->createOptionUsing(function (array $data) {
                                $taxonomyId = Taxonomy::firstOrCreate(
                                    ['key' => 'media_category'],
                                    ['label' => 'Media Categories', 'hierarchical' => true],
                                )->id;

                                $base = filled($data['slug'] ?? null)
                                    ? Str::slug((string) $data['slug'])
                                    : Str::slug((string) ($data['name'] ?? ''));

                                $base = $base !== '' ? $base : 'category';

                                $slug = $base;
                                $i = 2;

                                while (Term::where('taxonomy_id', $taxonomyId)->where('slug', $slug)->exists()) {
                                    $slug = $base . '-' . $i;
                                    $i++;
                                }

                                $term = Term::create([
                                    'taxonomy_id' => $taxonomyId,
                                    'name' => (string) $data['name'],
                                    'slug' => $slug,
                                    'parent_id' => $data['parent_id'] ?? null,
                                ]);

                                return $term->getKey();
                            })
                            ->columnSpanFull(),

                        Section::make('Attachment Settings')
                            ->description('Public page URL is /{slug}. You can hide it per media like WordPress.')
                            ->collapsible()
                            ->collapsed()
                            ->schema([
                                Toggle::make('attachment_public')
                                    ->label('Public attachment page')
                                    ->helperText('If OFF, visiting /{slug} returns 404 (even if global setting is enabled).')
                                    ->default(true),

                                Toggle::make('attachment_indexable')
                                    ->label('Indexable (SEO)')
                                    ->helperText('If OFF, robots meta becomes noindex, follow.')
                                    ->default(true),

                                TextInput::make('alt')
                                    ->label('Alt text')
                                    ->maxLength(255),

                                Placeholder::make('attachment_url_preview')
                                    ->label('Attachment URL')
                                    ->content(function (?Media $record, Get $get) {
                                        $slug = $record?->slug ?: trim((string) $get('slug'), '/');
                                        if ($slug === '') {
                                            return '';
                                        }

                                        return url('/' . ltrim($slug, '/'));
                                    })
                                    ->helperText('This is the public attachment page URL (if enabled + public).'),
                            ])
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $replace = $data['replace_file'] ?? null;

        if (is_array($replace)) {
            $replace = collect($replace)->first();
        }

        if ($replace instanceof TemporaryUploadedFile || $replace instanceof UploadedFile) {
            $this->pendingReplaceFile = $replace;
        }

        unset($data['replace_file']);

        $this->pendingCategoryTermIds = isset($data['category_term_ids']) && is_array($data['category_term_ids'])
            ? collect($data['category_term_ids'])
                ->filter(fn($id) => is_numeric($id) && (int) $id > 0)
                ->map(fn($id) => (int) $id)
                ->unique()
                ->values()
                ->all()
            : [];

        unset($data['category_term_ids']);

        if (array_key_exists('slug', $data)) {
            $newSlug = trim((string) ($data['slug'] ?? ''));
            if ($newSlug === '') {
                unset($data['slug']);
            } else {
                $data['slug'] = Str::slug($newSlug);
            }
        }

        if (array_key_exists('attachment_public', $data)) {
            $data['attachment_public'] = (bool) $data['attachment_public'];
        }
        if (array_key_exists('attachment_indexable', $data)) {
            $data['attachment_indexable'] = (bool) $data['attachment_indexable'];
        }

        if (isset($data['meta']) && !is_array($data['meta'])) {
            $data['meta'] = [];
        }

        // ✅ KEEP: if Product still empty, fill from selected category product (don’t overwrite)
        $currentProduct = trim((string) data_get($data, 'meta.frontend.product', ''));
        if ($currentProduct === '' && !empty($this->pendingCategoryTermIds)) {
            $guess = $this->guessProductFromSelectedCategory($this->pendingCategoryTermIds);
            if ($guess !== '') {
                data_set($data, 'meta.frontend.product', $guess);
            }
        }

        return $data;
    }

    protected function afterSave(): void
    {
        /** @var Media $record */
        $record = $this->record;

        $record->syncCategoryTerms($this->pendingCategoryTermIds);

        if (!$this->pendingReplaceFile) {
            return;
        }

        app(MediaUploader::class)->replace($record, $this->pendingReplaceFile);

        $record->refresh();
        $this->fillForm();

        $this->pendingReplaceFile = null;

        Notification::make()
            ->title('File replaced.')
            ->success()
            ->send();

        $this->dispatch('$refresh');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('delete')
                ->label('Delete')
                ->icon('heroicon-o-trash')
                ->color('danger')
                ->requiresConfirmation()
                ->action(function (): void {
                    $this->record->delete();

                    Notification::make()
                        ->title('Deleted.')
                        ->success()
                        ->send();

                    $this->redirect(MediaResource::getUrl('index'));
                }),

            Action::make('regenerate')
                ->label('Regenerate variants')
                ->icon('heroicon-o-arrow-path')
                ->color('gray')
                ->visible(fn(Media $record) => $record->isImage())
                ->action(function (Media $record): void {
                    $job = GenerateMediaVariants::dispatch($record->id, true);

                    $queueEnabled = (bool) config('cms-media.queue.enabled', true);
                    if ($queueEnabled) {
                        $connection = (string) config('cms-media.queue.connection', config('queue.default'));
                        $queue = (string) config('cms-media.queue.queue', 'media');
                        $job->onConnection($connection)->onQueue($queue);
                    }

                    Notification::make()
                        ->title('Variant regeneration queued.')
                        ->success()
                        ->send();
                }),

            Action::make('visit')
                ->label('Visit')
                ->icon('heroicon-o-arrow-top-right-on-square')
                ->color('primary')
                ->visible(fn(Media $record): bool => filled($record->slug))
                ->disabled(function (Media $record): bool {
                    // Block for private category
                    if ($this->isFrontendBlockedByPrivateCategory($record)) {
                        return true;
                    }

                    // Existing attachment enabled/public checks
                    $settings = app(SettingsRepository::class);
                    $enabled = (bool) $settings->get('core', 'attachment_pages_enabled', false);

                    return !($enabled && (bool) $record->attachment_public);
                })
                ->tooltip(function (Media $record): ?string {
                    if ($this->isFrontendBlockedByPrivateCategory($record)) {
                        return 'This is a private link.';
                    }

                    $settings = app(SettingsRepository::class);
                    $enabled = (bool) $settings->get('core', 'attachment_pages_enabled', false);

                    if (!($enabled && (bool) $record->attachment_public)) {
                        return 'Attachment page is disabled or not public.';
                    }

                    return null;
                })
                ->url(fn(Media $record): string => url('/' . ltrim((string) $record->slug, '/')))
                ->openUrlInNewTab(),

            Action::make('back')
                ->label('Back')
                ->icon('heroicon-o-arrow-left')
                ->color('gray')
                ->url(MediaResource::getUrl('index')),

            Action::make('saveHeader')
                ->label('Save changes')
                ->icon('heroicon-o-check')
                ->color('primary')
                ->keyBindings(['mod+s'])
                ->action(fn() => $this->save()),
        ];
    }
}