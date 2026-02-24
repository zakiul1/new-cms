<?php

namespace Plugins\StaticPosts\Filament\Resources\StaticCategories;

use App\Models\Term;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Plugins\StaticPosts\Filament\Resources\StaticCategories\Pages\CreateStaticCategory;
use Plugins\StaticPosts\Filament\Resources\StaticCategories\Pages\EditStaticCategory;
use Plugins\StaticPosts\Filament\Resources\StaticCategories\Pages\ListStaticCategories;
use Plugins\StaticPosts\Filament\Resources\StaticCategories\Schemas\StaticCategoryForm;
use Plugins\StaticPosts\Filament\Resources\StaticCategories\Tables\StaticCategoriesTable;

class StaticCategoryResource extends Resource
{
    protected static ?string $model = Term::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTag;

    protected static ?string $navigationLabel = 'Static Category';

    // ✅ FIX: Filament v5 expects UnitEnum|string|null
    protected static string|\UnitEnum|null $navigationGroup = 'Static Posts';

    protected static ?int $navigationSort = 3;

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->whereHas('taxonomy', fn(Builder $q) => $q->where('key', 'static_category'))
            ->withCount([
                'posts as items_count' => fn(Builder $q) => $q->where('type', 'static_post'),
            ]);
    }

    public static function form(Schema $schema): Schema
    {
        return StaticCategoryForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return StaticCategoriesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListStaticCategories::route('/'),
            'create' => CreateStaticCategory::route('/create'),
            'edit' => EditStaticCategory::route('/{record}/edit'),
        ];
    }
}