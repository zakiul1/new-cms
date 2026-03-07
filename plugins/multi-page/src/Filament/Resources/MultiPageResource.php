<?php

namespace Plugins\MultiPage\Filament\Resources;

use App\Models\Post;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Plugins\MultiPage\Filament\Resources\MultiPageResource\Pages\CreateMultiPage;
use Plugins\MultiPage\Filament\Resources\MultiPageResource\Pages\EditMultiPage;
use Plugins\MultiPage\Filament\Resources\MultiPageResource\Pages\ListMultiPages;
use Plugins\MultiPage\Filament\Resources\MultiPageResource\Tables\MultiPagesTable;
use Plugins\MultiPage\Filament\Schemas\MultiPageForm;
use UnitEnum;

class MultiPageResource extends Resource
{
    protected static ?string $model = Post::class;

    protected static ?string $modelLabel = 'Mega Page';
    protected static ?string $pluralModelLabel = 'Mega Pages';

    protected static ?string $recordTitleAttribute = 'title';

    public static function shouldRegisterNavigation(): bool
    {
        return true;
    }

    public static function getNavigationLabel(): string
    {
        return 'Mega Pages';
    }

    public static function getNavigationGroup(): UnitEnum|string|null
    {
        return 'Mega Post';
    }

    public static function getNavigationSort(): ?int
    {
        return 50;
    }

    public static function getNavigationIcon(): ?string
    {
        return 'heroicon-o-squares-plus';
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('type', 'multipage');
    }

    public static function form(Schema $schema): Schema
    {
        return MultiPageForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return MultiPagesTable::configure($table);
    }

    public static function canCreate(): bool
    {
        return true;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMultiPages::route('/'),
            'create' => CreateMultiPage::route('/create'),
            'edit' => EditMultiPage::route('/{record}/edit'),
        ];
    }
}