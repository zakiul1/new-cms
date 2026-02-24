<?php

namespace Plugins\StaticPosts\Filament\Resources\StaticPosts\Tables;


use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Actions\BulkActionGroup;

use Filament\Actions\DeleteBulkAction;

use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\HtmlString;
use Plugins\StaticPosts\Filament\Resources\StaticPosts\StaticPostResource;
use Filament\Actions\DeleteAction;

class StaticPostsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            // ✅ Make the whole <tr> a "group row"
            ->recordClasses(fn() => ['group/row'])

            ->columns([
                TextColumn::make('title')
                    ->label('Title')
                    ->searchable()
                    ->sortable()
                    ->html()
                    ->formatStateUsing(function ($state, $record) {
                        $editUrl = StaticPostResource::getUrl('edit', ['record' => $record]);
                        $viewUrl = url('/static/' . $record->slug);

                        // ✅ Now it responds to full row hover (group/row)
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

                TextColumn::make('status')
                    ->badge()
                    ->sortable(),

                TextColumn::make('updated_at')
                    ->since()
                    ->sortable(),
            ])
            ->actions([
                EditAction::make(),
                Action::make('view')
                    ->label('View')
                    ->icon('heroicon-o-eye')
                    ->url(fn($record) => url('/static/' . $record->slug))
                    ->openUrlInNewTab(),
                DeleteAction::make()
                    ->label('Delete') // or 'Trash'
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