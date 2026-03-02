<?php

namespace App\Filament\Resources\Categories\Tables;

use App\Cms\Content\PermalinkManager;
use App\Filament\Resources\Categories\CategoryResource;
use App\Models\Post;
use App\Models\Term;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\HtmlString;

class CategoriesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            // ✅ Needed for hover UI (group-hover)
            ->recordClasses(fn() => 'group')
            ->defaultSort('id', 'desc')
            ->columns([
                // ✅ NEW: Category ID column
                TextColumn::make('id')
                    ->label('ID')
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('name')
                    ->label('Name')
                    ->sortable()
                    ->searchable()

                    // ✅ NEW: Show count like "T-shirt (25)"
                    // Requires CategoryResource::getEloquentQuery() to load items_count
                    ->formatStateUsing(function (string $state, Term $record): string {
                        $count = (int) ($record->items_count ?? 0);
                        return "{$state} ({$count})";
                    })

                    // ✅ WP-like hover actions under name
                    ->description(function (Term $record, PermalinkManager $permalinks): HtmlString {
                        $editUrl = CategoryResource::getUrl('edit', ['record' => $record]);

                        // Most systems use /category/{slug}. If yours is different,
                        // update PermalinkManager::termUrl() or replace this with cms_term_url($record).
                        $viewUrl = method_exists($permalinks, 'termUrl')
                            ? $permalinks->termUrl($record)
                            : (function () use ($record) {
                            // fallback
                            return function_exists('cms_term_url')
                                ? cms_term_url($record)
                                : url('/category/' . ltrim((string) $record->slug, '/'));
                        })();

                        return new HtmlString(
                            '<div class="mt-1 text-xs text-slate-500 opacity-0 transition group-hover:opacity-100">' .
                            '<a class="hover:underline text-primary-600" href="' . e($editUrl) . '">Edit</a>' .
                            ' <span class="text-slate-300">|</span> ' .
                            '<a class="hover:underline text-slate-600" href="' . e($viewUrl) . '" target="_blank" rel="noopener noreferrer">View</a>' .
                            '</div>'
                        );
                    }),

                TextColumn::make('slug')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('parent.name')
                    ->label('Parent')
                    ->toggleable(),

                TextColumn::make('visibility')
                    ->label('Visibility')
                    ->badge()
                    ->sortable(),

                TextColumn::make('updated_at')
                    ->since()
                    ->sortable(),
            ])
            ->recordActions([
                EditAction::make(),

                Action::make('view')
                    ->label('View')
                    ->icon('heroicon-o-eye')
                    ->url(function (Term $record, PermalinkManager $permalinks) {
                        if (method_exists($permalinks, 'termUrl')) {
                            return $permalinks->termUrl($record);
                        }

                        if (function_exists('cms_term_url')) {
                            return cms_term_url($record);
                        }

                        return url('/category/' . ltrim((string) $record->slug, '/'));
                    }, true)
                    ->openUrlInNewTab(),

                DeleteAction::make()
                    ->label('Trash'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    // ✅ NEW: Copy posts from selected categories → target category (attach only)
                    BulkAction::make('copyItemsToCategory')
                        ->label('Copy items to…')
                        ->icon('heroicon-o-document-duplicate')
                        ->form([
                            Select::make('target_term_id')
                                ->label('Target category')
                                ->required()
                                ->searchable()
                                ->options(
                                    fn() => Term::query()
                                        ->whereHas('taxonomy', fn($q) => $q->where('key', 'category'))
                                        ->orderBy('name')
                                        ->pluck('name', 'id')
                                        ->toArray()
                                ),
                        ])
                        ->action(function (Collection $records, array $data): void {
                            $targetId = (int) $data['target_term_id'];

                            if ($records->isEmpty()) {
                                return;
                            }

                            $sourceIds = $records->pluck('id')->map(fn($v) => (int) $v)->values()->all();

                            // Collect all post IDs currently in ANY of the selected categories
                            $postIds = DB::table('termables')
                                ->whereIn('term_id', $sourceIds)
                                ->where('termable_type', Post::class)
                                ->pluck('termable_id')
                                ->unique()
                                ->values()
                                ->all();

                            if (empty($postIds)) {
                                return;
                            }

                            // Attach to target category without detaching from source categories
                            $rows = array_map(fn($postId) => [
                                'term_id' => $targetId,
                                'termable_type' => Post::class,
                                'termable_id' => (int) $postId,
                            ], $postIds);

                            DB::table('termables')->insertOrIgnore($rows);
                        }),

                    // ✅ NEW: Move posts from selected categories → target category (detach selected + attach target)
                    BulkAction::make('moveItemsToCategory')
                        ->label('Move items to…')
                        ->icon('heroicon-o-arrow-right-circle')
                        ->color('warning')
                        ->form([
                            Select::make('target_term_id')
                                ->label('Target category')
                                ->required()
                                ->searchable()
                                ->options(
                                    fn() => Term::query()
                                        ->whereHas('taxonomy', fn($q) => $q->where('key', 'category'))
                                        ->orderBy('name')
                                        ->pluck('name', 'id')
                                        ->toArray()
                                ),
                        ])
                        ->action(function (Collection $records, array $data): void {
                            $targetId = (int) $data['target_term_id'];

                            if ($records->isEmpty()) {
                                return;
                            }

                            $sourceIds = $records->pluck('id')->map(fn($v) => (int) $v)->values()->all();

                            // Collect all post IDs currently in ANY of the selected categories
                            $postIds = DB::table('termables')
                                ->whereIn('term_id', $sourceIds)
                                ->where('termable_type', Post::class)
                                ->pluck('termable_id')
                                ->unique()
                                ->values()
                                ->all();

                            if (empty($postIds)) {
                                return;
                            }

                            DB::transaction(function () use ($sourceIds, $targetId, $postIds) {
                                // 1) Detach ONLY the selected categories from these posts
                                DB::table('termables')
                                    ->whereIn('term_id', $sourceIds)
                                    ->where('termable_type', Post::class)
                                    ->whereIn('termable_id', $postIds)
                                    ->delete();

                                // 2) Attach to target category
                                $rows = array_map(fn($postId) => [
                                    'term_id' => $targetId,
                                    'termable_type' => Post::class,
                                    'termable_id' => (int) $postId,
                                ], $postIds);

                                DB::table('termables')->insertOrIgnore($rows);
                            });
                        }),

                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}