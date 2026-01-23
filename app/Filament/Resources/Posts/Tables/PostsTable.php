<?php

namespace App\Filament\Resources\Posts\Tables;

use App\Models\Taxonomy;
use App\Models\Term;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class PostsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')
                    ->label('Title')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('categories.name')
                    ->label('Categories')
                    ->listWithLineBreaks()
                    ->limitList(3)
                    ->toggleable(),

                TextColumn::make('status')
                    ->badge()
                    ->sortable(),

                TextColumn::make('updated_at')
                    ->label('Updated')
                    ->since()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('category')
                    ->label('Category')
                    ->options(function (): array {
                        $taxonomyId = Taxonomy::where('key', 'category')->value('id');
                        if (!$taxonomyId) {
                            return [];
                        }

                        return Term::query()
                            ->where('taxonomy_id', $taxonomyId)
                            ->orderBy('name')
                            ->pluck('name', 'id')
                            ->all();
                    })
                    ->query(function ($query, array $data) {
                        $termId = $data['value'] ?? null;
                        if (!$termId) {
                            return $query;
                        }

                        return $query->whereHas('categories', fn($q) => $q->whereKey($termId));
                    }),
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