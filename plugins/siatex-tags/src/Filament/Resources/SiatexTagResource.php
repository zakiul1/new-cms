<?php

namespace Plugins\SiatexTags\Filament\Resources;

use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\SelectColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\HtmlString;
use Plugins\SiatexTags\Filament\Resources\SiatexTagResource\Pages;
use Plugins\SiatexTags\Models\SiatexTag;
use Plugins\SiatexTags\Support\MediaCategoryOptions;
use UnitEnum;

class SiatexTagResource extends Resource
{
    protected static ?string $model = SiatexTag::class;

    protected static ?string $modelLabel = 'Siatex Tag';

    protected static ?string $pluralModelLabel = 'Siatex Tags';

    protected static string|UnitEnum|null $navigationGroup = 'Tags';

    protected static ?int $navigationSort = 57;

    protected static ?string $navigationLabel = 'Siatex Tags';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-tag';

    protected static ?string $recordTitleAttribute = 'title';

    public static function form(Schema $schema): Schema
    {
        return $schema;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->recordClasses(fn() => 'group')
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('id')
                    ->label('ID')
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('title')
                    ->label('H1')
                    ->searchable()
                    ->sortable()
                    ->wrap()
                    ->description(function (SiatexTag $record): HtmlString {
                        $editUrl = static::getUrl('edit', ['record' => $record]);

                        $viewUrl = function_exists('cms_slug_url')
                            ? cms_slug_url((string) $record->slug)
                            : url('/' . trim((string) $record->slug, '/') . '/');

                        return new HtmlString(
                            '<div class="mt-1 text-xs text-slate-500 opacity-0 transition group-hover:opacity-100">' .
                            '<a class="text-primary-600 hover:underline" href="' . e($editUrl) . '">Edit</a>' .
                            '<span class="mx-2 text-slate-300">|</span>' .
                            '<a class="text-slate-600 hover:underline" href="' . e($viewUrl) . '" target="_blank" rel="noopener noreferrer">View</a>' .
                            '</div>'
                        );
                    }),

                TextColumn::make('slug')
                    ->label('Slug')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->toggleable(),

                SelectColumn::make('media_category_term_id')
                    ->label('Media Category')
                    ->options(fn(): array => MediaCategoryOptions::options())
                    ->searchable()
                    ->placeholder('Select…')
                    ->toggleable(),
            ])
            ->actions([
                Action::make('edit')
                    ->label('Edit')
                    ->icon('heroicon-o-pencil-square')
                    ->url(fn(SiatexTag $record) => static::getUrl('edit', ['record' => $record])),

                Action::make('view')
                    ->label('View')
                    ->icon('heroicon-o-eye')
                    ->url(fn(SiatexTag $record) => function_exists('cms_slug_url')
                        ? cms_slug_url((string) $record->slug)
                        : url('/' . trim((string) $record->slug, '/') . '/'))
                    ->openUrlInNewTab(),

                DeleteAction::make()
                    ->label('Trash')
                    ->icon('heroicon-o-trash')
                    ->requiresConfirmation(),
            ])
            ->recordUrl(null);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSiatexTags::route('/'),
            'create' => Pages\CreateSiatexTag::route('/create'),
            'edit' => Pages\EditSiatexTag::route('/{record}/edit'),
        ];
    }
}