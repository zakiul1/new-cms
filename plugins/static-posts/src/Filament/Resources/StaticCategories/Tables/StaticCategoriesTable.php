<?php

namespace Plugins\StaticPosts\Filament\Resources\StaticCategories\Tables;

use Filament\Tables;
use Filament\Tables\Table;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\BulkActionGroup;

class StaticCategoriesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')->sortable(),
                Tables\Columns\TextColumn::make('name')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('parent.name')->label('Parent')->toggleable(),
                Tables\Columns\TextColumn::make('visibility')->badge()->sortable(),
                Tables\Columns\TextColumn::make('updated_at')->since()->sortable(),
                Tables\Columns\TextColumn::make('items_count')->label('Items')->sortable(),
            ])
            ->actions([
                EditAction::make(),
                ViewAction::make()->visible(false),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}