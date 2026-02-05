<?php

namespace App\Filament\Resources\Categories\Tables;

use App\Cms\Content\PermalinkManager;
use App\Filament\Resources\Categories\CategoryResource;
use App\Models\Term;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\HtmlString;

class CategoriesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            // ✅ Needed for hover UI (group-hover)
            ->recordClasses(fn() => 'group')

            ->columns([
                TextColumn::make('name')
                    ->label('Name')
                    ->sortable()
                    ->searchable()

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
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}