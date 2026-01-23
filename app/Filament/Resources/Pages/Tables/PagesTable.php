<?php

namespace App\Filament\Resources\Pages\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class PagesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')
                    ->label('Title')
                    ->searchable()
                    ->sortable()
                    ->wrap()
                    ->url(fn($record) => route('filament.admin.resources.pages.edit', ['record' => $record])),

                TextColumn::make('status')
                    ->badge()
                    ->sortable(),

                TextColumn::make('author.name')
                    ->label('Author')
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('slug')
                    ->label('Slug')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('published_at')
                    ->label('Published')
                    ->dateTime('Y-m-d H:i')
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('updated_at')
                    ->label('Updated')
                    ->since()
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'draft' => 'Draft',
                        'published' => 'Published',
                        'scheduled' => 'Scheduled',
                    ]),

                SelectFilter::make('author_id')
                    ->label('Author')
                    ->relationship('author', 'name'),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}