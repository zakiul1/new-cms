<?php

namespace App\Filament\Resources\Pages\Tables;

use App\Cms\Content\PermalinkManager;
use App\Filament\Resources\Pages\PageResource;
use App\Models\Post;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\HtmlString;

class PagesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            // ✅ Needed for hover UI (group-hover)
            ->recordClasses(fn() => 'group')

            ->columns([
                TextColumn::make('title')
                    ->label('Title')
                    ->searchable()
                    ->sortable()
                    ->wrap(false)
                    ->limit(50)
                    ->tooltip(fn($record) => $record->title)
                    ->extraAttributes(['class' => 'max-w-[420px] truncate'])

                    // ✅ WP-like hover actions
                    ->description(function (Post $record, PermalinkManager $permalinks): HtmlString {
                        $editUrl = PageResource::getUrl('edit', ['record' => $record]);
                        $viewUrl = $permalinks->pageUrl($record);

                        return new HtmlString(
                            '<div class="mt-1 text-xs text-slate-500 opacity-0 transition group-hover:opacity-100">' .
                            '<a class="hover:underline text-primary-600" href="' . e($editUrl) . '">Edit</a>' .
                            ' <span class="text-slate-300">|</span> ' .
                            '<a class="hover:underline text-slate-600" href="' . e($viewUrl) . '" target="_blank" rel="noopener noreferrer">View</a>' .
                            '</div>'
                        );
                    }),

                // ✅ Permalink
                TextColumn::make('url')
                    ->label('URL')
                    ->state(fn(Post $record, PermalinkManager $permalinks) => $permalinks->pageUrl($record))
                    ->url(fn(Post $record, PermalinkManager $permalinks) => $permalinks->pageUrl($record), true)
                    ->limit(60)
                    ->tooltip(fn(Post $record, PermalinkManager $permalinks) => $permalinks->pageUrl($record))
                    ->toggleable(isToggledHiddenByDefault: true),

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

            // ✅ Row actions on the right
            ->recordActions([
                EditAction::make(),

                Action::make('view')
                    ->label('View')
                    ->icon('heroicon-o-eye')
                    ->url(fn(Post $record, PermalinkManager $permalinks) => $permalinks->pageUrl($record), true)
                    ->openUrlInNewTab(),

                DeleteAction::make()
                    ->label('Trash'),
            ])

            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}