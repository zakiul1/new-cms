<?php

namespace Plugins\MultiPage\Filament\Resources;

use App\Models\Post;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Plugins\MultiPage\Filament\Resources\MultiPageResource\Pages\CreateMultiPage;
use Plugins\MultiPage\Filament\Resources\MultiPageResource\Pages\EditMultiPage;
use Plugins\MultiPage\Filament\Resources\MultiPageResource\Pages\ListMultiPages;
use Plugins\MultiPage\Filament\Schemas\MultiPageForm;
use UnitEnum;

class MultiPageResource extends Resource
{
    protected static ?string $model = Post::class;

    protected static ?string $modelLabel = 'Multi Page';
    protected static ?string $pluralModelLabel = 'Multi Pages';

    protected static ?string $recordTitleAttribute = 'title';

    public static function shouldRegisterNavigation(): bool
    {
        return true;
    }

    public static function getNavigationLabel(): string
    {
        return 'Multi Pages';
    }

    public static function getNavigationGroup(): UnitEnum|string|null
    {
        return 'Pages';
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
        return $table
            // ✅ Fix row click URL
            ->recordUrl(fn(Post $record): string => static::getUrl('edit', ['record' => $record], panel: 'admin'))

            ->columns([
                TextColumn::make('title')
                    ->label('Title')
                    ->searchable()
                    ->sortable()
                    // ✅ Fix title link (and "Edit" under title)
                    ->url(fn(Post $record): string => static::getUrl('edit', ['record' => $record], panel: 'admin')),

                TextColumn::make('status')
                    ->badge()
                    ->sortable(),

                TextColumn::make('author.name')
                    ->label('Author')
                    ->toggleable()
                    ->sortable(),

                TextColumn::make('published_at')
                    ->label('Published')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('updated_at')
                    ->label('Updated')
                    ->since()
                    ->sortable(),
            ])

            // ✅ Filament v5: use recordActions() with Filament\Actions\* classes
            ->recordActions([
                EditAction::make()
                    ->url(fn(Post $record): string => static::getUrl('edit', ['record' => $record], panel: 'admin')),

                ViewAction::make()
                    ->url(fn(Post $record): string => url('/' . ltrim((string) $record->slug, '/')))
                    ->openUrlInNewTab(),

                DeleteAction::make(),
            ])

            ->defaultSort('updated_at', 'desc');
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