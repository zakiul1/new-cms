<?php

namespace App\Filament\Resources\Posts\Schemas;

use App\Cms\Content\PermalinkManager;
use App\Filament\Forms\Components\MediaPicker;
use App\Filament\Forms\Components\WpClassicEditor;
use App\Models\Post;
use App\Models\Taxonomy;
use App\Models\Term;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
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
    'xl' => 12,
])

            ->components([
               Tabs::make('Editor')
    ->columnSpan([
        'default' => 1,
        'xl' => 9,
    ])

                    ->tabs([
                        Tab::make('Content')
                            ->schema([
                                TextInput::make('title')
                                    ->label('Post Title')

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

                                TextInput::make('meta_json.slider.title')
                                    ->label('H1')
                                    ->maxLength(255)
                                    ->live(onBlur: true),

                                TextInput::make('slug')
                                    ->label('Slug')
                                    ->maxLength(255)
                                    ->regex('/^[a-z0-9]+(?:-[a-z0-9]+)*$/')
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(function ($state, Set $set) {
                                        $set('slug', filled($state) ? Str::slug((string) $state) : null);
                                    })
                                    ->dehydrateStateUsing(fn($state) => filled($state) ? Str::slug((string) $state) : null)
                                    ->rule(function (?Post $record) {
                                        return Rule::unique('posts', 'slug')->ignore($record?->id);
                                    }),

                                Placeholder::make('permalink_preview')
                                    ->label('')
                                    ->content(function (?Post $record, Get $get, PermalinkManager $permalinks) {
                                        if ($record) {
                                            return $permalinks->postUrl($record);
                                        }

                                        $base = rtrim((string) config('app.url'), '/');

                                        $slug = trim((string) $get('slug'), '/');
                                        if ($slug === '') {
                                            $slug = Str::slug((string) ($get('title') ?? ''));
                                        }
                                        $slug = $slug !== '' ? $slug : 'your-page-slug';

                                        return $base . '/' . $slug;
                                    }),

                                WpClassicEditor::make('content_json')
                                    ->label('Hero Section')
                                    ->height(320)
                                    ->columnSpanFull()
                                    ->formatStateUsing(function ($state): string {
                                        if (is_array($state)) {
                                            $html = $state['html'] ?? '';
                                            return is_string($html) ? $html : '';
                                        }

                                        return is_string($state) ? $state : '';
                                    })
                                    ->dehydrateStateUsing(function ($state, Get $get): array {
                                        $current = $get('content_json');

                                        if (!is_array($current)) {
                                            $current = [];
                                        }

                                        $current['html'] = is_string($state) ? $state : '';

                                        return $current;
                                    }),

                                WpClassicEditor::make('meta_json.product')
                                    ->label('Product')
                                    ->height(180)
                                    ->columnSpanFull()
                                    ->formatStateUsing(function ($state): string {
                                        if (is_array($state)) {
                                            $html = $state['html'] ?? '';
                                            return is_string($html) ? $html : '';
                                        }

                                        return is_string($state) ? $state : '';
                                    })
                                    ->dehydrateStateUsing(function ($state) {
                                        return is_string($state) ? $state : '';
                                    }),

                                WpClassicEditor::make('meta_json.sub_description')
                                    ->label('Promo')
                                    ->height(180)
                                    ->columnSpanFull()
                                    ->formatStateUsing(function ($state): string {
                                        if (is_array($state)) {
                                            $html = $state['html'] ?? '';
                                            return is_string($html) ? $html : '';
                                        }

                                        return is_string($state) ? $state : '';
                                    })
                                    ->dehydrateStateUsing(function ($state) {
                                        return is_string($state) ? $state : '';
                                    }),

                                Section::make('SEO (Premium)')
                                    ->description('Control how this page appears in Google and when shared on social media.')
                                    ->collapsible()
                                    ->collapsed()
                                    ->schema([
                                        TextInput::make('meta_json.seo.title')
                                            ->label('SEO Title')
                                            ->maxLength(1000)
                                            ->live(onBlur: true),

                                        Textarea::make('meta_json.seo.description')
                                            ->label('Meta Description')
                                            ->rows(3)
                                            ->maxLength(2000)
                                            ->live(onBlur: true),

                                        TextInput::make('meta_json.seo.canonical')
                                            ->label('Canonical URL (optional)')
                                            ->placeholder('https://example.com/your-page')
                                            ->helperText('Leave empty to auto-use the current URL.')
                                            ->maxLength(255),

                                        Select::make('meta_json.seo.robots')
                                            ->label('Robots')
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
                                            ->placeholder('https://example.com/og.jpg')
                                            ->maxLength(255),
                                    ])
                                    ->columnSpanFull(),
                            ]),

                        Tab::make('Custom CSS & JS')
                            ->schema([
                                Textarea::make('meta_json.assets.css')
                                    ->label('Custom CSS (Paste Raw CSS Without <style> tags)')
                                    ->helperText('Applies to this page only. Output inside <head>.')
                                    ->rows(14)
                                    ->extraAttributes([
                                        'style' => 'font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;',
                                    ])
                                    ->live(onBlur: true),

                                Textarea::make('meta_json.assets.js')
                                    ->label('Custom JS (Paste Script Without <script> tags)')
                                    ->helperText('Applies to this page only. Output before </body>.')
                                    ->rows(14)
                                    ->extraAttributes([
                                        'style' => 'font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;',
                                    ])
                                    ->live(onBlur: true),

                                Textarea::make('meta_json.custom_json')
                                    ->label('Custom JSON (Paste Valid JSON)')
                                    ->helperText('Valid JSON only. Saved per page. (Do not include <script> tag)')
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
                                    ->content(function (?Post $record, PermalinkManager $permalinks): \Illuminate\Support\HtmlString {
                                        if (!$record) {
                                            return new \Illuminate\Support\HtmlString(
                                                '<div class="text-sm text-gray-600">Save the page first to preview the real frontend page.</div>'
                                            );
                                        }

                                        $url = $permalinks->postUrl($record);

                                        return new \Illuminate\Support\HtmlString(
                                            '<iframe src="' . e($url) . '" class="w-full rounded-xl border" style="height: 70vh;"></iframe>'
                                        );
                                    })
                                    ->dehydrated(false),
                            ]),
                    ]),

               Section::make('Publish')
    ->columnSpan([
        'default' => 1,
        'xl' => 3,
    ])

                    ->schema([
                        Select::make('status')
                            ->label('Status')
                            ->options([
                                'draft' => 'Draft',
                                'published' => 'Published',
                                'scheduled' => 'Scheduled',
                            ])
                            ->default('published')
                            ->required(),

                        Select::make('meta_json.template')
                            ->label('Template')
                            ->helperText('If selected, frontend will use that template file. If empty, theme default page view is used.')
                            ->options([
                                '' => 'Select an option',
                                'default' => 'Default',
                            ])
                            ->default('')
                            ->native(false)
                            ->dehydrateStateUsing(function ($state) {
                                return is_string($state) ? $state : '';
                            }),

                        MediaPicker::make('featured_media_ids')
                            ->label('Featured Images')
                            ->modalHeading('Featured images')
                            ->multiple()
                            ->maxItems(20),

                        MediaPicker::make('product_media_ids')
                            ->label('Product Images')
                            ->modalHeading('Product images')
                            ->multiple()
                            ->maxItems(50),

                        Select::make('parent_id')
                            ->label('Parent Page (optional)')
                            ->searchable()
                            ->preload()
                            ->nullable()
                            ->options(function (?Post $record): array {
                                return Post::query()
                                    ->where('type', 'page')
                                    ->when($record, fn($q) => $q->where('id', '!=', $record->id))
                                    ->orderBy('title')
                                    ->pluck('title', 'id')
                                    ->all();
                            }),

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
                                    ->afterStateUpdated(fn($state, Set $set) => $set('slug', filled($state) ? Str::slug((string) $state) : null))
                                    ->dehydrateStateUsing(fn($state) => filled($state) ? Str::slug((string) $state) : null)
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
                    ]),
            ]);
    }
}