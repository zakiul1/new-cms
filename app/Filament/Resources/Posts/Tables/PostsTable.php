<?php

namespace App\Filament\Resources\Posts\Tables;

use App\Cms\Content\PermalinkManager;
use App\Filament\Resources\Posts\PostResource;
use App\Models\Post;
use App\Models\Taxonomy;
use App\Models\Term;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\HtmlString;

class PostsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->recordClasses(fn() => 'group')
            ->defaultSort('updated_at', 'desc')
            ->columns([
                TextColumn::make('title')
                    ->label('Title')
                    ->searchable()
                    ->sortable()
                    ->wrap(false)
                    ->limit(50)
                    ->tooltip(fn(Post $record) => $record->title)
                    ->extraAttributes(['class' => 'max-w-[420px] truncate'])
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
                    ->label('Status')
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
                    BulkAction::make('copy_to_category')
                        ->label('Copy to Category')
                        ->icon('heroicon-o-document-duplicate')
                        ->form([
                            Select::make('category_id')
                                ->label('Target Category')
                                ->options(function (): array {
                                    $taxonomyId = Taxonomy::idByKey('category');

                                    if (!$taxonomyId) {
                                        return [];
                                    }

                                    return Term::query()
                                        ->where('taxonomy_id', $taxonomyId)
                                        ->orderBy('name')
                                        ->pluck('name', 'id')
                                        ->all();
                                })
                                ->searchable()
                                ->required(),
                        ])
                        ->action(function (Collection $records, array $data): void {
                            $taxonomyId = Taxonomy::idByKey('category');
                            $categoryId = (int) ($data['category_id'] ?? 0);

                            if (!$taxonomyId || $categoryId <= 0) {
                                return;
                            }

                            $valid = Term::query()
                                ->where('taxonomy_id', $taxonomyId)
                                ->where('id', $categoryId)
                                ->exists();

                            if (!$valid) {
                                Notification::make()
                                    ->title('Invalid category selected')
                                    ->danger()
                                    ->send();

                                return;
                            }

                            foreach ($records as $post) {
                                /** @var Post $post */
                                $post->terms()->syncWithoutDetaching([$categoryId]);
                            }
                        })
                        ->deselectRecordsAfterCompletion(),

                    BulkAction::make('move_to_category')
                        ->label('Move to Category')
                        ->icon('heroicon-o-arrow-right')
                        ->color('warning')
                        ->requiresConfirmation()
                        ->form([
                            Select::make('category_id')
                                ->label('Target Category')
                                ->options(function (): array {
                                    $taxonomyId = Taxonomy::idByKey('category');

                                    if (!$taxonomyId) {
                                        return [];
                                    }

                                    return Term::query()
                                        ->where('taxonomy_id', $taxonomyId)
                                        ->orderBy('name')
                                        ->pluck('name', 'id')
                                        ->all();
                                })
                                ->searchable()
                                ->required(),
                        ])
                        ->action(function (Collection $records, array $data): void {
                            $taxonomyId = Taxonomy::idByKey('category');
                            $categoryId = (int) ($data['category_id'] ?? 0);

                            if (!$taxonomyId || $categoryId <= 0) {
                                return;
                            }

                            $valid = Term::query()
                                ->where('taxonomy_id', $taxonomyId)
                                ->where('id', $categoryId)
                                ->exists();

                            if (!$valid) {
                                Notification::make()
                                    ->title('Invalid category selected')
                                    ->danger()
                                    ->send();

                                return;
                            }

                            $allCategoryTermIds = Term::query()
                                ->where('taxonomy_id', $taxonomyId)
                                ->pluck('id')
                                ->all();

                            foreach ($records as $post) {
                                /** @var Post $post */
                                if (!empty($allCategoryTermIds)) {
                                    $post->terms()->detach($allCategoryTermIds);
                                }

                                $post->terms()->syncWithoutDetaching([$categoryId]);
                            }
                        })
                        ->deselectRecordsAfterCompletion(),

                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}