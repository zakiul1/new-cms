<?php

namespace App\Filament\Resources\Posts\Schemas;

use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Components\Section;
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
                            ->afterStateUpdated(function ($state, callable $set, callable $get) {
                                if (!filled($get('slug'))) {
                                    $set('slug', Str::slug((string) $state));
                                }
                            }),

                        TextInput::make('slug')
                            ->label('Slug (optional)')
                            ->helperText('Leave blank to auto-generate. Duplicates will auto-rename.')
                            ->maxLength(255),

                        Textarea::make('excerpt')
                            ->rows(6),
                    ]),

                // RIGHT (1/3)
                Section::make('Publish')
                    ->columnSpan([
                        'default' => 1,
                        'lg' => 1,
                    ])
                    ->schema([
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
                                    ->afterStateUpdated(function ($state, callable $set, callable $get) {
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
                                    ->options(function () {
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
                                    'parent_id' => $data['parent_id'] ?? null, // ✅ save parent if chosen
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