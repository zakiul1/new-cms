<?php

namespace App\Filament\Resources\Pages\Schemas;

use App\Cms\Content\PermalinkManager;
use App\Filament\Forms\Components\WpClassicEditor;
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
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class PageForm
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
                                        if (!filled($get('slug'))) {
                                            $set('slug', Str::slug((string) $state));
                                        }

                                        if (!filled($get('meta_json.seo.title'))) {
                                            $set('meta_json.seo.title', (string) $state);
                                        }
                                    }),

                                // ✅ GLOBAL uniqueness + slug safety
                                TextInput::make('slug')
                                    ->label('Slug (optional)')
                                    ->helperText('Leave blank to auto-generate. Must be globally unique (posts + pages).')
                                    ->maxLength(255)
                                    ->regex('/^[a-z0-9]+(?:-[a-z0-9]+)*$/')
                                    // ✅ sanitize on blur
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(function ($state, Set $set) {
                                        $set('slug', filled($state) ? Str::slug((string) $state) : null);
                                    })
                                    ->dehydrateStateUsing(fn($state) => filled($state) ? Str::slug((string) $state) : null)
                                    ->rule(function (?Post $record) {
                                        // ✅ Global across all posts table rows
                                        return Rule::unique('posts', 'slug')->ignore($record?->id);
                                    }),

                                // ✅ Show actual frontend URL (uses permalink settings)
                                Placeholder::make('permalink_preview')
                                    ->label('Permalink')
                                    ->content(function (?Post $record, PermalinkManager $permalinks) {
                                        return $record
                                            ? $permalinks->pageUrl($record)
                                            : 'Will be generated after saving.';
                                    }),

                                Textarea::make('excerpt')
                                    ->rows(3)
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(function ($state, Set $set, Get $get) {
                                        if (!filled($get('meta_json.seo.description'))) {
                                            $text = trim((string) $state);
                                            if ($text !== '') {
                                                $set('meta_json.seo.description', Str::limit($text, 160, ''));
                                            }
                                        }
                                    }),

                                WpClassicEditor::make('content_json')
                                    ->label('Content')
                                    ->height(320)
                                    ->columnSpanFull()

                                    // ✅ editor always receives a STRING (not array)
                                    ->formatStateUsing(function ($state): string {
                                        if (is_array($state)) {
                                            $html = $state['html'] ?? '';
                                            return is_string($html) ? $html : '';
                                        }

                                        return is_string($state) ? $state : '';
                                    })

                                    // ✅ when saving, convert string back into array for content_json
                                    ->dehydrateStateUsing(function ($state, \Filament\Schemas\Components\Utilities\Get $get): array {
                                        $current = $get('content_json');

                                        if (!is_array($current)) {
                                            $current = [];
                                        }

                                        $current['html'] = is_string($state) ? $state : '';

                                        return $current;
                                    }),


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
                                    ->content(function (?Post $record, Get $get, PermalinkManager $permalinks): HtmlString {
                                        $title = trim((string) ($get('title') ?? ''));
                                        $title = $title !== '' ? $title : (string) ($record?->title ?: 'Page');

                                        $excerpt = trim((string) ($get('excerpt') ?? ''));

                                        $url = $record
                                            ? $permalinks->pageUrl($record)
                                            : '';

                                        $html = view('filament.pages.frontend-preview', [
                                            'title' => $title,
                                            'excerpt' => $excerpt,
                                            'url' => $url,
                                        ])->render();

                                        return new HtmlString($html);
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

                        Select::make('meta_json.template')
                            ->label('Template')
                            ->options([
                                'default' => 'Default',
                                'home' => 'Home',
                                'about' => 'About',
                                'landing' => 'Landing',
                            ])
                            ->default('default')
                            ->native(false),

                        Select::make('meta_json.parent_id')
                            ->label('Parent Page (optional)')
                            ->searchable()
                            ->preload()
                            ->nullable()
                            ->options(function (): array {
                                return Post::query()
                                    ->where('type', 'page')
                                    ->orderBy('title')
                                    ->pluck('title', 'id')
                                    ->all();
                            }),

                        TextInput::make('meta_json.menu_order')
                            ->label('Order')
                            ->helperText('Lower numbers appear first (like WordPress menu order).')
                            ->numeric()
                            ->default(0),

                        // ✅ Categories (kept as you had it, but validation fixed)
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

                                // ✅ FIX: unique within CATEGORY taxonomy (taxonomy_id), not global
                                TextInput::make('slug')
                                    ->required()
                                    ->maxLength(255)
                                    ->regex('/^[a-z0-9]+(?:-[a-z0-9]+)*$/')
                                    ->dehydrateStateUsing(fn($state) => Str::slug((string) $state))
                                    ->helperText('Lowercase letters, numbers, and hyphens only.')
                                    ->rule(function (?Term $record) {
                                        $taxonomyId = Taxonomy::where('key', 'category')->value('id');

                                        if (!$taxonomyId) {
                                            // taxonomy may not exist yet; createOptionUsing will enforce uniqueness
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

                                // ✅ Ensure unique within taxonomy
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