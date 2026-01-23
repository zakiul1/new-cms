<?php

namespace App\Filament\Resources\Tags\Tables;

use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class TagsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->sortable()->searchable(),
                TextColumn::make('slug')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')->since()->sortable(),
            ])
            ->actions([
                EditAction::make(),
            ])
            ->bulkActions([
                DeleteBulkAction::make(),
            ]);
    }
}