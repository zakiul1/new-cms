<?php

namespace Plugins\StaticPosts\Filament\Resources\StaticCategories\Schemas;

use App\Models\Taxonomy;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class StaticCategoryForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(['default' => 1, 'lg' => 3])
            ->components([
                \Filament\Schemas\Components\Section::make('Static Category')
                    ->columnSpan(['default' => 1, 'lg' => 2])
                    ->schema([
                        TextInput::make('name')
                            ->required()
                            ->maxLength(255)
                            ->live(onBlur: true)
                            ->afterStateUpdated(function ($state, callable $set, callable $get) {
                                if (!filled($get('slug'))) {
                                    $set('slug', Str::slug((string) $state));
                                }
                                if (!filled($get('product'))) {
                                    $set('product', (string) $state);
                                }
                            }),

                        TextInput::make('product')
                            ->label('Product')
                            ->maxLength(255)
                            ->nullable(),

                        TextInput::make('slug')
                            ->label('Slug (optional)')
                            ->maxLength(255)
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn($state, callable $set) => $set('slug', filled($state) ? Str::slug((string) $state) : null))
                            ->dehydrateStateUsing(fn($state) => filled($state) ? Str::slug((string) $state) : null)
                            ->nullable(),

                        Textarea::make('description')
                            ->rows(4)
                            ->nullable(),

                        Select::make('parent_id')
                            ->label('Parent')
                            ->searchable()
                            ->preload()
                            ->options(
                                fn() => \App\Models\Term::query()
                                    ->whereHas('taxonomy', fn(Builder $q) => $q->where('key', 'static_category'))
                                    ->pluck('name', 'id')
                                    ->all()
                            )
                            ->nullable(),
                    ])
                    ->columns(1),

                \Filament\Schemas\Components\Section::make('Visibility')
                    ->columnSpan(['default' => 1, 'lg' => 1])
                    ->schema([
                        Select::make('visibility')
                            ->options([
                                'public' => 'public',
                                'private' => 'private',
                            ])
                            ->default('public')
                            ->required(),
                    ]),
            ]);
    }
}