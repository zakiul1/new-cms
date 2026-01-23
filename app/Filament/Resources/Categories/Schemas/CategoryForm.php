<?php

namespace App\Filament\Resources\Categories\Schemas;

use App\Models\Taxonomy;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class CategoryForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(['default' => 1, 'lg' => 3])
            ->components([
                \Filament\Schemas\Components\Section::make('Category')
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
                            }),

                        TextInput::make('slug')
                            ->label('Slug (optional)')
                            ->helperText('Leave blank to auto-generate. Duplicates will auto-rename.')
                            ->maxLength(255),

                        Textarea::make('description')
                            ->rows(6),
                    ]),

                \Filament\Schemas\Components\Section::make('Parent')
                    ->columnSpan(['default' => 1, 'lg' => 1])
                    ->schema([
                        Select::make('parent_id')
                            ->label('Parent Category')
                            ->nullable()
                            ->searchable()
                            ->preload()
                            ->options(function () {
                                $taxonomyId = Taxonomy::where('key', 'category')->value('id');
                                if (!$taxonomyId) {
                                    return [];
                                }

                                return \App\Models\Term::query()
                                    ->where('taxonomy_id', $taxonomyId)
                                    ->orderBy('name')
                                    ->pluck('name', 'id')
                                    ->all();
                            })
                            ->disableOptionWhen(function ($value, $state, $get, $record) {
                                // prevent selecting itself as parent
                                return $record?->id && (int) $value === (int) $record->id;
                            }),
                    ]),
            ]);
    }
}