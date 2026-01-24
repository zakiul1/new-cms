<?php

namespace App\Filament\Resources\MediaResource\Tables;

use App\Models\Media;
use App\Models\Taxonomy;
use App\Models\Term;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class MediaTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('thumb')
                    ->label('')
                    ->getStateUsing(fn(Media $record) => $record->thumbUrl())
                    ->square(),

                TextColumn::make('title')
                    ->searchable()
                    ->sortable()
                    ->description(fn(Media $record) => $record->original_filename ?: null),

                TextColumn::make('mime_type')
                    ->label('Type')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('size')
                    ->label('Size')
                    ->formatStateUsing(fn($state) => number_format(((int) $state) / 1024, 1) . ' KB')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('folders')
                    ->label('Folder')
                    ->getStateUsing(function (Media $record) {
                        $folders = $record->folders()->pluck('name')->all();
                        return $folders ? implode(', ', $folders) : 'Uncategorized';
                    })
                    ->toggleable(),

                TextColumn::make('created_at')
                    ->since()
                    ->sortable(),
            ])
            ->filters([
                // ✅ Images / Video / PDF
                SelectFilter::make('type')
                    ->label('Type')
                    ->options([
                        'images' => 'Images',
                        'video' => 'Video',
                        'pdf' => 'PDF',
                        'other' => 'Other',
                    ])
                    ->query(function (Builder $query, array $data) {
                        $value = $data['value'] ?? null;

                        return match ($value) {
                            'images' => $query->where('mime_type', 'like', 'image/%'),
                            'video' => $query->where('mime_type', 'like', 'video/%'),
                            'pdf' => $query->where('mime_type', '=', 'application/pdf'),
                            'other' => $query->where(function (Builder $q) {
                                    $q->where('mime_type', 'not like', 'image/%')
                                    ->where('mime_type', 'not like', 'video/%')
                                    ->where('mime_type', '!=', 'application/pdf');
                                }),
                            default => $query,
                        };
                    }),

                // ✅ Folder filter
                SelectFilter::make('folder')
                    ->label('Folder')
                    ->options(function (): array {
                        $taxonomyId = Taxonomy::query()->where('key', 'media_folder')->value('id');
                        if (!$taxonomyId) {
                            return [];
                        }

                        return Term::query()
                            ->where('taxonomy_id', $taxonomyId)
                            ->orderBy('name')
                            ->pluck('name', 'id')
                            ->all();
                    })
                    ->query(function (Builder $query, array $data) {
                        $folderId = (int) ($data['value'] ?? 0);
                        if ($folderId <= 0) {
                            return $query;
                        }

                        return $query->whereHas('terms', fn(Builder $q) => $q->where('terms.id', $folderId));
                    }),

                // ✅ Date range filter
                Filter::make('date')
                    ->form([
                        \Filament\Forms\Components\DatePicker::make('from')->label('From'),
                        \Filament\Forms\Components\DatePicker::make('until')->label('Until'),
                    ])
                    ->query(function (Builder $query, array $data) {
                        return $query
                            ->when($data['from'] ?? null, fn(Builder $q, $date) => $q->whereDate('created_at', '>=', $date))
                            ->when($data['until'] ?? null, fn(Builder $q, $date) => $q->whereDate('created_at', '<=', $date));
                    }),
            ])
            ->defaultSort('id', 'desc');
    }
}