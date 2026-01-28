<?php

namespace App\Filament\Resources;

use App\Filament\Resources\NotFoundHitResource\Pages;
use App\Models\NotFoundHit;
use Filament\Actions;
use Filament\Resources\Resource;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;

class NotFoundHitResource extends Resource
{
    protected static ?string $model = NotFoundHit::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-exclamation-triangle';
    protected static string|\UnitEnum|null $navigationGroup = 'SEO';
    protected static ?string $navigationLabel = '404 Monitor';
    protected static ?int $navigationSort = 20;

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('path')
                    ->label('Missing URL')
                    ->searchable()
                    ->sortable()
                    ->wrap(),

                TextColumn::make('hits')
                    ->label('Hits')
                    ->sortable(),

                TextColumn::make('last_hit_at')
                    ->label('Last hit')
                    ->dateTime()
                    ->sortable(),

                TextColumn::make('last_referrer')
                    ->label('Referrer')
                    ->wrap()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('last_ip')
                    ->label('IP')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->recordActions([
                // One-click: create redirect from this missing path
                Actions\Action::make('create_redirect')
                    ->label('Create Redirect')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->url(fn(NotFoundHit $record) => route('filament.admin.resources.redirects.create', [
                        'from_path' => $record->path,
                    ])),
                Actions\DeleteAction::make(),
            ])
            ->defaultSort('hits', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListNotFoundHits::route('/'),
        ];
    }
}