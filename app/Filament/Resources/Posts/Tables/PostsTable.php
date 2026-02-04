<?php

namespace App\Filament\Resources\Posts\Tables;

use App\Cms\Content\PermalinkManager;
use App\Filament\Resources\Posts\PostResource;
use App\Models\Post;
use App\Models\Taxonomy;
use App\Models\Term;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\HtmlString;

class PostsTable
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
                        $editUrl = PostResource::getUrl('edit', ['record' => $record]);
                        $viewUrl = $permalinks->postUrl($record);

                        return new HtmlString(
                            '<div class="mt-1 text-xs text-slate-500 opacity-0 transition group-hover:opacity-100">' .
                            '<a class="hover:underline text-primary-600" href="' . e($editUrl) . '">Edit</a>' .
                            ' <span class="text-slate-300">|</span> ' .
                            '<a class="hover:underline text-slate-600" href="' . e($viewUrl) . '" target="_blank" rel="noopener noreferrer">View</a>' .
                            '</div>'
                        );
                    }),

                // ✅ Permalink (uses current permalink settings)
                TextColumn::make('url')
                    ->label('URL')
                    ->state(fn(Post $record, PermalinkManager $permalinks) => $permalinks->postUrl($record))
                    ->url(fn(Post $record, PermalinkManager $permalinks) => $permalinks->postUrl($record), true)
                    ->limit(60)
                    ->tooltip(fn(Post $record, PermalinkManager $permalinks) => $permalinks->postUrl($record))
                    ->toggleable(isToggledHiddenByDefault: true),

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

            // ✅ Right-side row actions (Edit + View + Trash)
            ->recordActions([
                EditAction::make(),

                Action::make('view')
                    ->label('View')
                    ->icon('heroicon-o-eye')
                    ->url(fn(Post $record, PermalinkManager $permalinks) => $permalinks->postUrl($record), true)
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