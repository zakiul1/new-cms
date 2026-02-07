<?php

namespace App\Filament\Resources;

use App\Filament\Resources\MediaCategoryResource\Pages;
use App\Models\Taxonomy;
use App\Models\Term;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\DeleteAction;
use UnitEnum;
use BackedEnum;

class MediaCategoryResource extends Resource
{
    protected static ?string $model = Term::class;

    // ✅ Show under Media group, under "Media"
    protected static string|UnitEnum|null $navigationGroup = 'Media';
    protected static ?int $navigationSort = 51;
    protected static ?string $navigationLabel = 'Media Categories';
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-tag';

    public static function getModelLabel(): string
    {
        return 'Media Category';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Media Categories';
    }

    protected static function mediaCategoryTaxonomyId(): int
    {
        return (int) Taxonomy::firstOrCreate(
            ['key' => 'media_category'],
            ['label' => 'Media Categories', 'hierarchical' => true],
        )->id;
    }

    public static function getEloquentQuery(): Builder
    {
        $taxonomyId = static::mediaCategoryTaxonomyId();

        return parent::getEloquentQuery()
            ->where('taxonomy_id', $taxonomyId);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns([
                'default' => 1,
                'lg' => 2,
            ])
            ->components([
                Section::make('Category')
                    ->schema([
                        TextInput::make('name')
                            ->required()
                            ->maxLength(255)
                            ->live(onBlur: true)
                            ->afterStateUpdated(function ($state, Set $set, Get $get) {
                                if (!filled($get('slug'))) {
                                    $set('slug', Str::slug((string) $state));
                                }

                                // ✅ auto-fill Product from Name (only if empty)
                                if (!filled($get('product'))) {
                                    $set('product', (string) $state);
                                }
                            }),

                        TextInput::make('product')
                            ->label('Product')
                            ->maxLength(255)
                            ->helperText('Defaults to Name. You can change it.'),


                        TextInput::make('slug')
                            ->label('Slug (optional)')
                            ->maxLength(255)
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn($state, Set $set) => $set('slug', filled($state) ? Str::slug((string) $state) : null))
                            ->dehydrateStateUsing(fn($state) => filled($state) ? Str::slug((string) $state) : null),

                        Select::make('visibility')
                            ->label('Visibility')
                            ->options([
                                'public' => 'Public (can be shown on frontend)',
                                'private' => 'Private (admin/internal only)',
                            ])
                            ->default('public')
                            ->required(),


                        Select::make('parent_id')
                            ->label('Parent (optional)')
                            ->searchable()
                            ->preload()
                            ->nullable()
                            ->options(function (): array {
                                $taxonomyId = static::mediaCategoryTaxonomyId();

                                return Term::query()
                                    ->where('taxonomy_id', $taxonomyId)
                                    ->orderBy('name')
                                    ->pluck('name', 'id')
                                    ->all();
                            }),
                    ])
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('name')
            ->columns([
                TextColumn::make('name')
                    ->label('Name')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('slug')
                    ->label('Slug')
                    ->toggleable()
                    ->sortable(),

                TextColumn::make('parent.name')
                    ->label('Parent')
                    ->toggleable(),
                TextColumn::make('visibility')
                    ->label('Visibility')
                    ->badge()
                    ->sortable(),

            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->bulkActions([
                DeleteBulkAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMediaCategories::route('/'),
            'create' => Pages\CreateMediaCategory::route('/create'),
            'edit' => Pages\EditMediaCategory::route('/{record}/edit'),
        ];
    }
}