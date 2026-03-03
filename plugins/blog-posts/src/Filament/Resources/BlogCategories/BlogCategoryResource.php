<?php

namespace Plugins\BlogPosts\Filament\Resources\BlogCategories;

use App\Models\Term;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Plugins\BlogPosts\Filament\Resources\BlogCategories\Pages\CreateBlogCategory;
use Plugins\BlogPosts\Filament\Resources\BlogCategories\Pages\EditBlogCategory;
use Plugins\BlogPosts\Filament\Resources\BlogCategories\Pages\ListBlogCategories;
use Plugins\BlogPosts\Filament\Resources\BlogCategories\Schemas\BlogCategoryForm;
use Plugins\BlogPosts\Filament\Resources\BlogCategories\Tables\BlogCategoriesTable;

class BlogCategoryResource extends Resource
{
    protected static ?string $model = Term::class;

    protected static ?string $modelLabel = 'Blog Category';
    protected static ?string $pluralModelLabel = 'Blog Categories';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedFolder;
    protected static ?string $navigationLabel = 'Blog Categories';

    // ✅ show under Blog Posts menu group
    protected static string|\UnitEnum|null $navigationGroup = 'Blog Posts';

    // ✅ order inside Blog Posts group
    protected static ?int $navigationSort = 3;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->whereHas('taxonomy', fn(Builder $q) => $q->where('key', 'blog_category'))
            ->withCount([
                'posts as items_count' => fn(Builder $q) => $q->where('type', 'blog_post'),
            ]);
    }

    public static function form(Schema $schema): Schema
    {
        return BlogCategoryForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return BlogCategoriesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListBlogCategories::route('/'),
            'create' => CreateBlogCategory::route('/create'),
            'edit' => EditBlogCategory::route('/{record}/edit'),
        ];
    }
}