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
use Filament\Actions\BulkAction;
use Filament\Forms\Components\Select;
use Illuminate\Database\Eloquent\Collection;
use Filament\Notifications\Notification;
use App\Models\Taxonomy;
use App\Models\Term;
use App\Cms\Core\SettingsRepository;

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
                    ->formatStateUsing(function (string $state, Post $record, SettingsRepository $settings): HtmlString {
                        $homepageId = $settings->get('core', 'homepage_page_id', null);
                        $homepageId = is_numeric($homepageId) ? (int) $homepageId : null;

                        $title = e($state);

                        if ($homepageId !== null && (int) $record->id === $homepageId) {
                            $badge = '<span class="ml-2 inline-flex items-center rounded-md bg-blue-50 px-2 py-0.5 text-xs font-medium text-blue-700 ring-1 ring-inset ring-blue-600/20">Front Page</span>';
                            return new HtmlString($title . $badge);
                        }

                        return new HtmlString($title);
                    })
                    ->html()

                    // ✅ keep your existing WP-like hover actions
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

                            // Ensure selected term is really a "category" term
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

                            // Get all term IDs for the "category" taxonomy (so we only detach categories, not tags)
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