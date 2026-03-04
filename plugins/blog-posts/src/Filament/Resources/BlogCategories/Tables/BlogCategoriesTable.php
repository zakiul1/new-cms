<?php

namespace Plugins\BlogPosts\Filament\Resources\BlogCategories\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables;
use Filament\Tables\Table;

class BlogCategoriesTable
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