<?php

namespace Plugins\BlogPosts\Filament\Resources\BlogPosts\Tables;

use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class BlogPostsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->modifyQueryUsing(function (Builder $query) {
                return $query->with('categories');
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
                    ->formatStateUsing(function ($state, $record) {
                        $editUrl = \Plugins\BlogPosts\Filament\Resources\BlogPosts\BlogPostResource::getUrl('edit', ['record' => $record]);
                        $viewUrl = url('/blog/' . $record->slug);

                        return "<div class='space-y-1'>
                            <a class='font-semibold hover:underline' href='{$editUrl}'>{$state}</a>
                            <div class='text-xs text-gray-500 space-x-2'>
                                <a class='hover:underline' target='_blank' href='{$viewUrl}'>View</a>
                                <a class='hover:underline' href='{$editUrl}'>Edit</a>
                            </div>
                        </div>";
                    })
                    ->html(),

                Tables\Columns\TextColumn::make('categories')
                    ->label('Category')
                    ->formatStateUsing(function ($state, $record) {
                        return $record->categories?->pluck('name')->implode(', ') ?? '';
                    })
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
                Tables\Actions\Action::make('view')
                    ->label('View')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->url(fn($record) => url('/blog/' . $record->slug))
                    ->openUrlInNewTab(),

                Tables\Actions\EditAction::make(),

                Tables\Actions\DeleteAction::make()
                    ->requiresConfirmation(),
            ]);
    }
}