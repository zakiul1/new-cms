<?php

namespace Plugins\BlogPosts\Filament\Resources\BlogPosts;

use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Plugins\BlogPosts\Filament\Resources\BlogPosts\Pages\CreateBlogPost;
use Plugins\BlogPosts\Filament\Resources\BlogPosts\Pages\EditBlogPost;
use Plugins\BlogPosts\Filament\Resources\BlogPosts\Pages\ListBlogPosts;
use Plugins\BlogPosts\Filament\Resources\BlogPosts\Schemas\BlogPostForm;
use Plugins\BlogPosts\Filament\Resources\BlogPosts\Tables\BlogPostsTable;
use Plugins\BlogPosts\Models\BlogPost;

class BlogPostResource extends Resource
{
    protected static ?string $model = BlogPost::class;

    protected static ?string $modelLabel = 'Blog Post';
    protected static ?string $pluralModelLabel = 'Blog Posts';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;
    protected static ?string $navigationLabel = 'Blog Posts';

    // ✅ show as a separate menu group
    protected static string|\UnitEnum|null $navigationGroup = 'Blog Posts';

    // ✅ ensure Blog Posts group appears before Static Posts
    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'title';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('type', 'blog_post');
    }

    public static function form(Schema $schema): Schema
    {
        return BlogPostForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return BlogPostsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListBlogPosts::route('/'),
            'create' => CreateBlogPost::route('/create'),
            'edit' => EditBlogPost::route('/{record}/edit'),
        ];
    }
}