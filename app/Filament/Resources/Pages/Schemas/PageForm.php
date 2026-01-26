<?php

namespace App\Filament\Resources\Pages\Schemas;

use App\Models\Post;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DateTimePicker;
use Filament\Schemas\Components\Group;

use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\RichEditor;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

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
                            ->afterStateUpdated(function ($state, callable $set, Get $get) {
                                if (!filled($get('slug'))) {
                                    $set('slug', Str::slug((string) $state));
                                }
                            }),

                        Group::make()
                            ->columns([
                                'default' => 1,
                                'md' => 2,
                            ])
                            ->schema([
                                TextInput::make('slug')
                                    ->label('Slug (optional)')
                                    ->helperText('Leave blank to auto-generate. If duplicate, it will auto-rename (e.g. about-2).')
                                    ->maxLength(255),

                                Placeholder::make('permalink')
                                    ->label('Permalink')
                                    ->content(function (Get $get): string {
                                        $base = rtrim((string) config('app.url'), '/');
                                        $slug = trim((string) ($get('slug') ?? ''), '/');
                                        return $slug !== '' ? "{$base}/{$slug}" : "{$base}/";
                                    }),
                            ]),

                        Textarea::make('excerpt')
                            ->rows(5),

                        // ✅ WP-like classic editor (saved into content_json['html'])
                        RichEditor::make('content_json.html')
                            ->label('Content')
                            ->columnSpanFull()
                            ->extraAttributes([
                                // RichEditor::minHeight() doesn't exist in v5, so use CSS
                                'style' => 'min-height: 420px;',
                            ]),
                    ]),

                // RIGHT (1/3)
                Section::make('Publish')
                    ->columnSpan([
                        'default' => 1,
                        'lg' => 1,
                    ])
                    ->schema([
                        // ✅ WP-like: template + page attributes
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

                        // Your taxonomy
                        CheckboxList::make('categories')
                            ->label('Categories')
                            ->relationship('categories', 'name')
                            ->columns(1),

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

                        // ✅ SEO (WP-like)
                        Section::make('SEO')
                            ->collapsed()
                            ->schema([
                                TextInput::make('meta_json.seo.title')
                                    ->label('SEO Title')
                                    ->maxLength(70)
                                    ->helperText('Recommended: 50–60 characters.'),

                                Textarea::make('meta_json.seo.description')
                                    ->label('SEO Description')
                                    ->rows(3)
                                    ->maxLength(160)
                                    ->helperText('Recommended: 120–160 characters.'),
                            ]),
                    ]),
            ]);
    }
}