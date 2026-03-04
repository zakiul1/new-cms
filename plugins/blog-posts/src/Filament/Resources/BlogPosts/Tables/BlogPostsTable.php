<?php

namespace Plugins\BlogPosts\Filament\Resources\BlogPosts\Tables;

use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;

class BlogPostsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->modifyQueryUsing(function (Builder $query) {
                return $query->with(['categories']);
            })
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('ID')
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('title')
                    ->label('Title')
                    ->searchable()
                    ->sortable()
                    // ✅ Truncate in table, show full title on hover
                    ->formatStateUsing(function ($state, $record) {
                        $editUrl = \Plugins\BlogPosts\Filament\Resources\BlogPosts\BlogPostResource::getUrl('edit', ['record' => $record]);
                        $viewUrl = url('/blog/' . ltrim((string) $record->slug, '/'));

                        $full = (string) $state;
                        $short = mb_strlen($full) > 70 ? (mb_substr($full, 0, 70) . '…') : $full;

                        return new HtmlString(
                            "<div class='space-y-1' title='" . e($full) . "'>
                                <a class='font-semibold hover:underline' href='" . e($editUrl) . "'>" . e($short) . "</a>
                                <div class='text-xs text-gray-500 space-x-2'>
                                 <a class='hover:underline' href='" . e($editUrl) . "'>Edit</a>
                                    <a class='hover:underline' target='_blank' href='" . e($viewUrl) . "'>View</a>
                                   
                                </div>
                            </div>"
                        );
                    })
                    ->html(),

                Tables\Columns\TextColumn::make('categories')
                    ->label('Category')
                    ->formatStateUsing(function ($state, $record) {
                        return $record->categories?->pluck('name')->implode(', ') ?? '';
                    })
                    // ✅ copy category text
                    ->copyable()
                    ->copyMessage('Category copied')
                    ->toggleable(),

                Tables\Columns\BadgeColumn::make('status')
                    ->label('Status')
                    ->sortable()
                    ->colors([
                        'warning' => 'draft',
                        'success' => 'published',
                        'gray' => 'scheduled',
                    ]),
            ])
            ->actions([
                Action::make('view')
                    ->label('View')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->url(fn($record) => url('/blog/' . ltrim((string) $record->slug, '/')))
                    ->openUrlInNewTab(),

                EditAction::make(),

                DeleteAction::make()
                    ->requiresConfirmation(),
            ])
            // ✅ Bulk delete
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()->requiresConfirmation(),
                ]),
            ]);
    }
}