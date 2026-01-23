<?php

namespace App\Filament\Resources\MediaResource\Tables;

use App\Models\Media;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class MediaTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('thumb')
                    ->label('')
                    ->getStateUsing(fn (Media $record) => $record->thumbUrl() ?: $record->url())
                    ->square()
                    ->extraImgAttributes(['loading' => 'lazy'])
                    ->toggleable(),

                TextColumn::make('title')
                    ->label('Title')
                    ->searchable()
                    ->sortable()
                    ->wrap()
                    ->getStateUsing(function (Media $record) {
                        return $record->title ?: ($record->original_filename ?: ('Media #' . $record->id));
                    }),

                TextColumn::make('mime_type')
                    ->label('Type')
                    ->toggleable(isToggledHiddenByDefault: false)
                    ->searchable(),

                TextColumn::make('size')
                    ->label('Size')
                    ->alignRight()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->formatStateUsing(function ($state) {
                        $bytes = (int) ($state ?? 0);
                        if ($bytes <= 0) return '—';

                        $kb = $bytes / 1024;
                        if ($kb < 1024) {
                            return number_format($kb, 1) . ' KB';
                        }

                        $mb = $kb / 1024;
                        return number_format($mb, 2) . ' MB';
                    }),

                TextColumn::make('created_at')
                    ->label('Uploaded')
                    ->since()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('mime_group')
                    ->label('Type')
                    ->options([
                        'image' => 'Images',
                        'video' => 'Videos',
                        'audio' => 'Audio',
                        'application' => 'Documents',
                    ])
                    ->query(function ($query, array $data) {
                        $value = $data['value'] ?? null;
                        if (! $value) {
                            return $query;
                        }

                        return $query->where('mime_type', 'like', $value . '/%');
                    }),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('id', 'desc');
    }
}
