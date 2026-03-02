<?php

namespace Plugins\StaticPosts\Filament\Resources\StaticPosts\Tables;

use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\HtmlString;
use Plugins\StaticPosts\Filament\Resources\StaticPosts\StaticPostResource;

class StaticPostsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            // ✅ Make the whole <tr> a "group row"
            ->recordClasses(fn() => ['group/row'])
            ->defaultSort('id', 'desc')

            // ✅ Eager-load categories (needed for Category column)
            ->modifyQueryUsing(fn($query) => $query->with(['categories']))

            ->columns([
                // ✅ 1) Post ID column
                TextColumn::make('id')
                    ->label('ID')
                    ->sortable()
                    ->toggleable()
                    ->alignCenter(),

                // ✅ Title column (same as before)
                TextColumn::make('title')
                    ->label('Title')
                    ->searchable()
                    ->sortable()
                    ->html()
                    ->formatStateUsing(function ($state, $record) {
                        $editUrl = StaticPostResource::getUrl('edit', ['record' => $record]);
                        $viewUrl = url('/static/' . $record->slug);

                        return new HtmlString(
                            '<div>
                                <div class="font-medium text-gray-950 dark:text-white">
                                    ' . e((string) $state) . '
                                </div>

                                <div class="mt-1 hidden text-sm text-gray-600 dark:text-gray-300 group-hover/row:flex gap-2">
                                    <a href="' . e($editUrl) . '" class="text-primary-600 hover:underline">Edit</a>
                                    <span class="text-gray-400">|</span>
                                    <a href="' . e($viewUrl) . '" target="_blank" class="text-primary-600 hover:underline">View</a>
                                </div>
                            </div>'
                        );
                    }),

                // ✅ 3) Category column (header cell)
                TextColumn::make('categories.name')
                    ->label('Category')
                    ->html()
                    ->formatStateUsing(function ($state, $record) {
                        // handles multiple categories
                        $names = $record->categories?->pluck('name')->filter()->values()->all() ?? [];
                        return implode(', ', array_map('e', $names));
                    })
                    ->wrap()
                    ->toggleable(),

                // Status column (same as before)
                TextColumn::make('status')
                    ->badge()
                    ->sortable(),

                // ✅ 2) Removed updated_at column
            ])
            ->actions([
                EditAction::make(),

                Action::make('view')
                    ->label('View')
                    ->icon('heroicon-o-eye')
                    ->url(fn($record) => url('/static/' . $record->slug))
                    ->openUrlInNewTab(),

                DeleteAction::make()
                    ->label('Delete')
                    ->icon('heroicon-o-trash')
                    ->requiresConfirmation(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}