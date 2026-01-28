<?php

namespace App\Filament\Resources\Posts\Schemas;

use App\Filament\Forms\Components\MediaPicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

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
                // LEFT (2/3)
                Section::make('Content')
                    ->columnSpan([
                        'default' => 1,
                        'lg' => 2,
                    ])
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

                        TextInput::make('slug')
                            ->label('Slug (optional)')
                            ->helperText('Leave blank to auto-generate. Duplicates will auto-rename.')
                            ->maxLength(255),

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
                                    ->maxLength(70)
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

                                // If you prefer MediaPicker here, you can swap to MediaPicker
                                TextInput::make('meta_json.seo.og_image')
                                    ->label('OpenGraph Image (optional)')
                                    ->helperText('Absolute URL or path. Used for Facebook/Twitter previews.')
                                    ->placeholder('https://example.com/og.jpg')
                                    ->maxLength(255),
                            ])
                            ->columnSpanFull(),
                    ]),

                // RIGHT (1/3)
                Section::make('Publish')
                    ->columnSpan([
                        'default' => 1,
                        'lg' => 1,
                    ])
                    ->schema([
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
                                    ->maxLength(255),

                                Select::make('parent_id')
                                    ->label('Parent Category (optional)')
                                    ->searchable()
                                    ->preload()
                                    ->nullable()
                                    ->options(function (): array {
                                        $taxonomyId = \App\Models\Taxonomy::where('key', 'category')->value('id');
                                        if (!$taxonomyId) {
                                            return [];
                                        }

                                        return \App\Models\Term::query()
                                            ->where('taxonomy_id', $taxonomyId)
                                            ->orderBy('name')
                                            ->pluck('name', 'id')
                                            ->all();
                                    }),
                            ])
                            ->createOptionUsing(function (array $data) {
                                $taxonomyId = \App\Models\Taxonomy::firstOrCreate(
                                    ['key' => 'category'],
                                    ['label' => 'Categories', 'hierarchical' => true],
                                )->id;

                                $base = filled($data['slug'] ?? null)
                                    ? Str::slug((string) $data['slug'])
                                    : Str::slug((string) ($data['name'] ?? ''));

                                $base = $base !== '' ? $base : 'category';

                                $slug = $base;
                                $i = 2;

                                while (\App\Models\Term::where('taxonomy_id', $taxonomyId)->where('slug', $slug)->exists()) {
                                    $slug = $base . '-' . $i;
                                    $i++;
                                }

                                $term = \App\Models\Term::create([
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

                        Select::make('status')
                            ->options([
                                'draft' => 'Draft',
                                'published' => 'Published',
                                'scheduled' => 'Scheduled',
                            ])
                            ->default('draft')
                            ->required(),

                        DateTimePicker::make('published_at')
                            ->label('Publish At')
                            ->seconds(false),
                    ]),
            ]);
    }
}