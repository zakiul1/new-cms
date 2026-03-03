<?php

namespace Plugins\BlogPosts\Filament\Resources\BlogCategories\Schemas;

use App\Models\Taxonomy;
use App\Models\Term;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class BlogCategoryForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->schema([
            Grid::make(['default' => 12])
                ->schema([

                    Section::make()
                        ->columnSpan(['default' => 12])
                        ->schema([

                            TextInput::make('name')
                                ->required()
                                ->live(onBlur: true)
                                ->afterStateUpdated(function ($state, callable $set, callable $get) {
                                    if (!$get('slug')) {
                                        $set('slug', Str::slug($state ?? ''));
                                    }

                                    if (!$get('product')) {
                                        $set('product', $state ?? '');
                                    }
                                }),

                            TextInput::make('slug')
                                ->required()
                                ->maxLength(191)
                                ->live(onBlur: true)
                                ->afterStateUpdated(function ($state, callable $set) {
                                    $set('slug', Str::slug($state ?? ''));
                                }),

                            TextInput::make('product')
                                ->label('Product')
                                ->maxLength(191),

                            Textarea::make('description')
                                ->rows(4)
                                ->columnSpanFull(),

                            Select::make('parent_id')
                                ->label('Parent Category')
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
                                ->nullable(),

                            Select::make('visibility')
                                ->label('Visibility')
                                ->options([
                                    'public' => 'Public',
                                    'private' => 'Private',
                                ])
                                ->default('public')
                                ->required(),
                        ]),
                ]),
        ]);
    }
}