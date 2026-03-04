<?php

namespace Plugins\MultiPage\Filament\Resources\MultiPageResource\Tables;

use App\Cms\Content\PermalinkManager;
use App\Models\Post;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\HtmlString;
use Plugins\MultiPage\Filament\Resources\MultiPageResource;
use App\Models\Taxonomy;
use App\Models\Term;

class MultiPagesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            // ✅ Needed for hover UI (group-hover)
            ->recordClasses(fn() => 'group')

            // ✅ Fix row click URL (same behavior)
            ->recordUrl(fn(Post $record): string => MultiPageResource::getUrl('edit', ['record' => $record], panel: 'admin'))

            ->columns([
                TextColumn::make('title')
                    ->label('Title')
                    ->searchable()
                    ->sortable()
                    ->wrap(false)
                    ->limit(50)
                    ->tooltip(fn($record) => $record->title)
                    ->extraAttributes(['class' => 'max-w-[420px] truncate'])
                    ->formatStateUsing(function (string $state): HtmlString {
                        // ✅ No "Front Page" badge for multipage (page-only)
                        return new HtmlString(e($state));
                    })
                    ->html()

                    // ✅ same hover actions: Edit | View
                    ->description(function (Post $record, PermalinkManager $permalinks): HtmlString {
                        $editUrl = MultiPageResource::getUrl('edit', ['record' => $record], panel: 'admin');
                        $viewUrl = url('/' . ltrim((string) $record->slug, '/'));

                        return new HtmlString(
                            '<div class="mt-1 text-xs text-slate-500 opacity-0 transition group-hover:opacity-100">' .
                            '<a class="hover:underline text-primary-600" href="' . e($editUrl) . '">Edit</a>' .
                            ' <span class="text-slate-300">|</span> ' .
                            '<a class="hover:underline text-slate-600" href="' . e($viewUrl) . '" target="_blank" rel="noopener noreferrer">View</a>' .
                            '</div>'
                        );
                    }),

                // ✅ URL column (toggle hidden by default)
                TextColumn::make('url')
                    ->label('URL')
                    ->state(fn(Post $record) => url('/' . ltrim((string) $record->slug, '/')))
                    ->url(fn(Post $record) => url('/' . ltrim((string) $record->slug, '/')), true)
                    ->limit(60)
                    ->tooltip(fn(Post $record) => url('/' . ltrim((string) $record->slug, '/')))
                    ->toggleable(isToggledHiddenByDefault: true),

                // ✅ Keep Slug hidden by default if you still want it
                TextColumn::make('slug')
                    ->label('Slug')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                // ✅ Keep Updated only (remove Status, Author, Published)
                TextColumn::make('updated_at')
                    ->label('Updated')
                    ->since()
                    ->sortable()
                    ->toggleable(),
            ])

            // ✅ Filters removed (Status/Author filters were tied to removed columns)
            ->filters([])

            // ✅ Row actions on the right
            ->recordActions([
                EditAction::make()
                    ->url(fn(Post $record): string => MultiPageResource::getUrl('edit', ['record' => $record], panel: 'admin')),

                Action::make('view')
                    ->label('View')
                    ->icon('heroicon-o-eye')
                    ->url(fn(Post $record) => url('/' . ltrim((string) $record->slug, '/')), true)
                    ->openUrlInNewTab(),

                DeleteAction::make()
                    ->label('Trash'),
            ])

            // ✅ Toolbar bulk actions (keep if you still want category tools)
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
                                /** @var \App\Models\Post $post */
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
                                /** @var \App\Models\Post $post */
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