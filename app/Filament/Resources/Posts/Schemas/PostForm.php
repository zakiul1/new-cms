<?php

namespace App\Filament\Resources\Posts\Schemas;

use App\Cms\Content\PermalinkManager;
use App\Filament\Forms\Components\MediaPicker;
use App\Models\Post;
use App\Models\Taxonomy;
use App\Models\Term;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class PostForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns([
                'default' => 1,
                'lg' => 3,
            ])
            ->components([
                /**
                 * LEFT (2/3): Tabs
                 */
                Tabs::make('Editor')
                    ->columnSpan([
                        'default' => 1,
                        'lg' => 2,
                    ])
                    ->tabs([
                        Tab::make('Content')
                            ->schema([
                                TextInput::make('title')
                                    ->required()
                                    ->maxLength(255)
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(function ($state, Set $set, Get $get) {
                                        // slug fill (existing behavior)
                                        if (!filled($get('slug'))) {
                                            $set('slug', Str::slug((string) $state));
                                        }

                                        // ✅ SEO title fill (only if empty)
                                        if (!filled($get('meta_json.seo.title'))) {
                                            $set('meta_json.seo.title', (string) $state);
                                        }
                                    }),

                                // ✅ GLOBAL uniqueness + slug safety
                                TextInput::make('slug')
                                    ->label('Slug (optional)')
                                    ->maxLength(255)
                                    ->regex('/^[a-z0-9]+(?:-[a-z0-9]+)*$/')
                                    // ✅ sanitize on blur
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(function ($state, Set $set) {
                                        $set('slug', filled($state) ? Str::slug((string) $state) : null);
                                    })
                                    ->dehydrateStateUsing(fn($state) => filled($state) ? Str::slug((string) $state) : null)
                                    ->rule(function (?Post $record) {
                                        // ✅ Global across all posts table rows (posts + pages share same slug namespace)
                                        return Rule::unique('posts', 'slug')->ignore($record?->id);
                                    })
                                    ->helperText('Leave blank to auto-generate. Must be globally unique (posts + pages).'),

                                // ✅ Show actual frontend URL (uses Permalink Settings)
                                Placeholder::make('permalink_preview')
                                    ->label('Permalink')
                                    ->content(function (?Post $record, Get $get, PermalinkManager $permalinks) {
                                        // When editing an existing post, show the true permalink
                                        if ($record) {
                                            return $permalinks->postUrl($record);
                                        }

                                        // On create, we don't have ID/date yet; show a "preview"
                                        $base = rtrim((string) config('app.url'), '/');

                                        $slug = trim((string) $get('slug'), '/');
                                        if ($slug === '') {
                                            $slug = Str::slug((string) ($get('title') ?? ''));
                                        }
                                        $slug = $slug !== '' ? $slug : '(auto)';

                                        $structure = $permalinks->postStructure();

                                        // Plain mode uses query param
                                        if ($structure === 'plain') {
                                            return "{$base}/?p=(after-save)";
                                        }

                                        // Build a preview path by replacing tokens; ID not known yet
                                        $now = now();
                                        $preview = strtr($structure, [
                                            '%year%' => $now->format('Y'),
                                            '%monthnum%' => $now->format('m'),
                                            '%day%' => $now->format('d'),
                                            '%hour%' => $now->format('H'),
                                            '%minute%' => $now->format('i'),
                                            '%second%' => $now->format('s'),
                                            '%post_id%' => '(after-save)',
                                            '%postname%' => $slug,
                                        ]);

                                        $preview = '/' . ltrim($preview, '/');
                                        $preview = $preview !== '/' ? rtrim($preview, '/') : '/';

                                        return $base . $preview;
                                    }),

                                Textarea::make('excerpt')
                                    ->rows(3)
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(function ($state, Set $set, Get $get) {
                                        // ✅ SEO description fill (only if empty)
                                        if (!filled($get('meta_json.seo.description'))) {
                                            $text = trim((string) $state);
                                            if ($text !== '') {
                                                $set('meta_json.seo.description', Str::limit($text, 160, ''));
                                            }
                                        }
                                    }),

                                // ✅ Editor
                                RichEditor::make('content_json')
                                    ->label('Content')
                                    ->columnSpanFull()
                                    ->extraAttributes([
                                        'style' => 'min-height: 420px;',
                                    ]),

                                // ✅ SEO (Premium-feel)
                                Section::make('SEO (Premium)')
                                    ->description('Control how this page appears in Google and when shared on social media.')
                                    ->collapsible()
                                    ->collapsed()
                                    ->schema([
                                        TextInput::make('meta_json.seo.title')
                                            ->label('SEO Title')
                                            ->helperText('Recommended: ~50–60 characters.')
                                            ->maxLength(140)
                                            ->live(onBlur: true),

                                        Textarea::make('meta_json.seo.description')
                                            ->label('Meta Description')
                                            ->helperText('Recommended: ~150–160 characters.')
                                            ->rows(3)
                                            ->maxLength(200)
                                            ->live(onBlur: true),

                                        TextInput::make('meta_json.seo.canonical')
                                            ->label('Canonical URL (optional)')
                                            ->placeholder('https://example.com/your-page')
                                            ->helperText('Leave empty to auto-use the current URL.')
                                            ->maxLength(255),

                                        Select::make('meta_json.seo.robots')
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

                                        TextInput::make('meta_json.seo.og_image')
                                            ->label('OpenGraph Image (optional)')
                                            ->helperText('Absolute URL or path. Used for Facebook/Twitter previews.')
                                            ->placeholder('https://example.com/og.jpg')
                                            ->maxLength(255),
                                    ])
                                    ->columnSpanFull(),
                            ]),

                        Tab::make('Custom CSS & JS')
                            ->schema([
                                Textarea::make('meta_json.assets.css')
                                    ->label('Custom CSS (Paste Row CSS Without <style> tags)')
                                    ->helperText('Applies to this post only. Output inside <head>.')
                                    ->rows(14)
                                    ->extraAttributes([
                                        'style' => 'font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;',
                                    ])
                                    ->live(onBlur: true),

                                Textarea::make('meta_json.assets.js')
                                    ->label('Custom JS (Paste Script Without <script> tags)')
                                    ->helperText('Applies to this post only. Output before </body>.')
                                    ->rows(14)
                                    ->extraAttributes([
                                        'style' => 'font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;',
                                    ])
                                    ->live(onBlur: true),
                                Textarea::make('meta_json.custom_json')
                                    ->label('Custom JSON (Paste Valid JSON)')
                                    ->helperText('Valid JSON only. Saved per post. (Do not include <script> tag)')
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

                                        // keep legacy string JSON editable
                                        return (string) $state;
                                    })
                                    ->dehydrateStateUsing(function ($state) {
                                        $state = trim((string) $state);

                                        if ($state === '') {
                                            return null;
                                        }

                                        $decoded = json_decode($state, true);

                                        // extra safety (rules(['json']) should already prevent invalid)
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
                                    ->content(function (?Post $record, Get $get, PermalinkManager $permalinks) {
                                        // Basic preview HTML (safe + simple)
                                        $title = trim((string) ($get('title') ?? ''));
                                        $title = $title !== '' ? $title : (string) ($record?->title ?: 'Post');

                                        $slug = trim((string) ($get('slug') ?? ''));
                                        $slug = $slug !== '' ? Str::slug($slug) : (string) ($record?->slug ?? '');

                                        $excerpt = trim((string) ($get('excerpt') ?? ''));

                                        $url = $record
                                            ? $permalinks->postUrl($record)
                                            : ($slug !== '' ? url('/' . ltrim($slug, '/')) : '');

                                        $html = view('filament.posts.frontend-preview', [
                                            'title' => $title,
                                            'excerpt' => $excerpt,
                                            'url' => $url,
                                        ])->render();

                                        return new \Illuminate\Support\HtmlString($html);
                                    })
                                    ->dehydrated(false),
                            ]),
                    ]),

                /**
                 * RIGHT (1/3): Publish
                 */
                Section::make('Publish')
                    ->columnSpan([
                        'default' => 1,
                        'lg' => 1,
                    ])
                    ->schema([
                        Select::make('status')
                            ->options([
                                'draft' => 'Draft',
                                'published' => 'Published',
                                'scheduled' => 'Scheduled',
                            ])
                            ->default('published')
                            ->required(),

                        MediaPicker::make('featured_media_id')
                            ->label('Featured Image')
                            ->modalHeading('Featured image'),

                        MediaPicker::make('product_media_ids')
                            ->label('Product Gallery')
                            ->multiple()
                            ->maxItems(20),

                        Select::make('categories')
                            ->label('Categories')
                            ->relationship('categories', 'name')
                            ->multiple()
                            ->searchable()
                            ->preload()
                            ->optionsLimit(50)
                            ->createOptionForm([
                                TextInput::make('name')
                                    ->required()
                                    ->maxLength(255)
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(function ($state, Set $set, Get $get) {
                                        if (!filled($get('slug'))) {
                                            $set('slug', Str::slug((string) $state));
                                        }
                                    }),

                                TextInput::make('slug')
                                    ->label('Slug (optional)')
                                    ->helperText('Leave blank to auto-generate.')
                                    ->maxLength(255)
                                    ->regex('/^[a-z0-9]+(?:-[a-z0-9]+)*$/')
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(fn($state, Set $set) => $set('slug', Str::slug((string) $state)))
                                    ->dehydrateStateUsing(fn($state) => filled($state) ? Str::slug((string) $state) : null)
                                    // ✅ FIX: unique within CATEGORY taxonomy (taxonomy_id)
                                    ->rule(function (?Term $record) {
                                        $taxonomyId = Taxonomy::where('key', 'category')->value('id');

                                        if (!$taxonomyId) {
                                            return null;
                                        }

                                        return Rule::unique('terms', 'slug')
                                            ->where('taxonomy_id', $taxonomyId)
                                            ->ignore($record?->id);
                                    }),

                                Select::make('parent_id')
                                    ->label('Parent Category (optional)')
                                    ->searchable()
                                    ->preload()
                                    ->nullable()
                                    ->options(function (): array {
                                        $taxonomyId = Taxonomy::where('key', 'category')->value('id');
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
                                    ['key' => 'category'],
                                    ['label' => 'Categories', 'hierarchical' => true],
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
                            }),

                        Select::make('tags')
                            ->label('Tags')
                            ->relationship('tags', 'name')
                            ->multiple()
                            ->preload()
                            ->searchable(),

                        DateTimePicker::make('published_at')
                            ->label('Publish At')
                            ->seconds(false)
                            ->required(fn(Get $get) => (string) $get('status') === 'scheduled'),
                    ]),
            ]);
    }
}