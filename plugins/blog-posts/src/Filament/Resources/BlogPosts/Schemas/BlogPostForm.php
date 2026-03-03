<?php

namespace Plugins\BlogPosts\Filament\Resources\BlogPosts\Schemas;

use App\Cms\Content\Slugger;
use App\Models\Taxonomy;
use App\Models\Term;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Group;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Tabs;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ViewField;
use Filament\Schemas\Schema;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class BlogPostForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->schema([
            Grid::make(['default' => 12])
                ->schema([

                    // Main content (left)
                    Group::make()
                        ->columnSpan(['default' => 8])
                        ->schema([
                            Tabs::make('Content Tabs')
                                ->tabs([

                                    // --- Content ---
                                    Tabs\Tab::make('Content')
                                        ->schema([
                                            Section::make()
                                                ->schema([
                                                    TextInput::make('title')
                                                        ->required()
                                                        ->live(onBlur: true)
                                                        ->afterStateUpdated(function ($state, callable $set, callable $get) {
                                                            // Auto-generate slug only if slug is empty
                                                            if (!$get('slug')) {
                                                                $set('slug', Str::slug($state ?? ''));
                                                            }
                                                        }),

                                                    TextInput::make('slug')
                                                        ->required()
                                                        ->regex('/^[a-z0-9]+(?:-[a-z0-9]+)*$/')
                                                        ->maxLength(191)
                                                        ->live(onBlur: true)
                                                        ->afterStateUpdated(function ($state, callable $set) {
                                                            $set('slug', Str::slug($state ?? ''));
                                                        })
                                                        ->unique(
                                                            table: 'posts',
                                                            column: 'slug',
                                                            ignorable: fn($record) => $record,
                                                        ),

                                                    ViewField::make('permalink_preview')
                                                        ->view('filament.components.permalink-preview')
                                                        ->viewData([
                                                            'prefix' => url('/blog/'),
                                                            'field' => 'slug',
                                                        ]),

                                                    Textarea::make('excerpt')
                                                        ->rows(3)
                                                        ->columnSpanFull(),

                                                    // Editor field stored in content_json['html']
                                                    Textarea::make('content_json.html')
                                                        ->label('Content')
                                                        ->rows(14)
                                                        ->columnSpanFull(),

                                                    TextInput::make('meta_json.blog_posts.learn_more_url')
                                                        ->label('Learn More URL')
                                                        ->maxLength(255),
                                                ]),
                                        ]),

                                    // --- Custom CSS/JS ---
                                    Tabs\Tab::make('Custom CSS & JS')
                                        ->schema([
                                            Section::make()
                                                ->schema([
                                                    Textarea::make('meta_json.assets.css')
                                                        ->label('Custom CSS')
                                                        ->rows(10)
                                                        ->columnSpanFull(),

                                                    Textarea::make('meta_json.assets.js')
                                                        ->label('Custom JS')
                                                        ->rows(10)
                                                        ->columnSpanFull(),

                                                    Textarea::make('meta_json.custom_json')
                                                        ->label('Custom JSON')
                                                        ->rows(8)
                                                        ->columnSpanFull()
                                                        ->helperText('Valid JSON only. Will be stored as decoded array when possible.')
                                                        ->dehydrateStateUsing(function ($state) {
                                                            if (is_string($state)) {
                                                                $decoded = json_decode($state, true);
                                                                if (json_last_error() === JSON_ERROR_NONE) {
                                                                    return $decoded;
                                                                }
                                                            }
                                                            return $state;
                                                        }),
                                                ]),
                                        ]),

                                    // --- Frontend Preview ---
                                    Tabs\Tab::make('Frontend Preview')
                                        ->schema([
                                            Section::make()
                                                ->schema([
                                                    ViewField::make('frontend_link')
                                                        ->view('filament.components.frontend-link')
                                                        ->viewData([
                                                            'prefix' => url('/blog/'),
                                                            'field' => 'slug',
                                                        ]),
                                                ]),
                                        ]),
                                ]),
                        ]),

                    // Sidebar (right)
                    Group::make()
                        ->columnSpan(['default' => 4])
                        ->schema([

                            Section::make('Publish')
                                ->schema([
                                    Select::make('status')
                                        ->options([
                                            'draft' => 'Draft',
                                            'published' => 'Published',
                                            'scheduled' => 'Scheduled',
                                        ])
                                        ->default('draft')
                                        ->required(),

                                    Select::make('meta_json.template')
                                        ->label('Template')
                                        ->options([
                                            '' => 'Default',
                                        ])
                                        ->helperText('Optional template override used by theme.')
                                        ->default(''),

                                    // Category multi-select (taxonomy: blog_category)
                                    Select::make('blog_category_ids')
                                        ->label('Blog Categories')
                                        ->multiple()
                                        ->searchable()
                                        ->preload()
                                        ->options(function () {
                                            $taxonomy = Taxonomy::query()->where('key', 'blog_category')->first();
                                            if (!$taxonomy) {
                                                return [];
                                            }

                                            return Term::query()
                                                ->where('taxonomy_id', $taxonomy->getKey())
                                                ->orderBy('name')
                                                ->pluck('name', 'id')
                                                ->toArray();
                                        })
                                        ->afterStateHydrated(function (Select $component, $state, $record) {
                                            if (!$record) {
                                                return;
                                            }

                                            // Pre-fill selected category IDs from relationship
                                            $taxonomy = Taxonomy::query()->where('key', 'blog_category')->first();
                                            if (!$taxonomy) {
                                                return;
                                            }

                                            $ids = $record->terms()
                                                ->where('taxonomy_id', $taxonomy->getKey())
                                                ->pluck('terms.id')
                                                ->all();

                                            $component->state($ids);
                                        }),

                                    // Featured Media IDs
                                    Select::make('featured_media_ids')
                                        ->label('Featured Images')
                                        ->multiple()
                                        ->searchable()
                                        ->preload()
                                        ->options(function () {
                                            return \App\Models\Media::query()
                                                ->orderByDesc('id')
                                                ->limit(2000)
                                                ->pluck('title', 'id')
                                                ->toArray();
                                        })
                                        ->afterStateHydrated(function (Select $component, $state, $record) {
                                            if (!$record) {
                                                return;
                                            }

                                            $ids = \App\Models\PostMedia::query()
                                                ->where('post_id', $record->getKey())
                                                ->where('role', 'featured')
                                                ->orderBy('sort_order')
                                                ->pluck('media_id')
                                                ->all();

                                            $component->state($ids);
                                        }),

                                    // Product Media IDs
                                    Select::make('product_media_ids')
                                        ->label('Product Images')
                                        ->multiple()
                                        ->searchable()
                                        ->preload()
                                        ->options(function () {
                                            return \App\Models\Media::query()
                                                ->orderByDesc('id')
                                                ->limit(2000)
                                                ->pluck('title', 'id')
                                                ->toArray();
                                        })
                                        ->afterStateHydrated(function (Select $component, $state, $record) {
                                            if (!$record) {
                                                return;
                                            }

                                            $ids = \App\Models\PostMedia::query()
                                                ->where('post_id', $record->getKey())
                                                ->where('role', 'product')
                                                ->orderBy('sort_order')
                                                ->pluck('media_id')
                                                ->all();

                                            $component->state($ids);
                                        }),

                                    Textarea::make('meta_json.blog_posts.svg_icon')
                                        ->label('SVG Icon')
                                        ->rows(6)
                                        ->helperText('Paste raw SVG code.'),
                                ]),
                        ]),
                ]),
        ]);
    }
}