<?php
namespace Plugins\StaticPosts\Filament\Resources\StaticPosts;

use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Plugins\StaticPosts\Filament\Resources\StaticPosts\Pages\CreateStaticPost;
use Plugins\StaticPosts\Filament\Resources\StaticPosts\Pages\EditStaticPost;
use Plugins\StaticPosts\Filament\Resources\StaticPosts\Pages\ListStaticPosts;
use Plugins\StaticPosts\Filament\Resources\StaticPosts\Schemas\StaticPostForm;
use Plugins\StaticPosts\Filament\Resources\StaticPosts\Tables\StaticPostsTable;
use Plugins\StaticPosts\Models\StaticPost;

class StaticPostResource extends Resource
{
    protected static ?string $model = StaticPost::class;

    protected static ?string $modelLabel = 'Static Post';
    protected static ?string $pluralModelLabel = 'Static Posts';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;
    protected static ?string $navigationLabel = 'Static Posts';

    // ✅ correct type for Filament v5
    protected static string|\UnitEnum|null $navigationGroup = 'Static Posts';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'title';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('type', 'static_post');
    }

    public static function form(Schema $schema): Schema
    {
        return StaticPostForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return StaticPostsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListStaticPosts::route('/'),
            'create' => CreateStaticPost::route('/create'),
            'edit' => EditStaticPost::route('/{record}/edit'),
        ];
    }
}