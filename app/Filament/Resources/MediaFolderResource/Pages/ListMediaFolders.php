<?php

namespace App\Filament\Resources\MediaFolderResource\Pages;

use App\Cms\Media\MediaUploader;
use App\Filament\Resources\MediaFolderResource;
use App\Models\Term;
use Filament\Actions\CreateAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\DB;
use Filament\Actions\EditAction;
use Filament\Actions\Action;

class ListMediaFolders extends ListRecords
{
    protected static string $resource = MediaFolderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('New folder'),
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('name')
            ->columns([
                TextColumn::make('name')
                    ->label('Folder')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('parent.name')
                    ->label('Parent')
                    ->toggleable(),

                TextColumn::make('media_count')
                    ->label('Items')
                    ->counts('media')
                    ->sortable(),
            ])
            ->actions([
                EditAction::make(),

                // ✅ HARD delete with typed confirmation + deletes media inside
                Action::make('deleteFolder')
                    ->label('Delete')
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Delete folder? This will delete ALL media inside.')
                    ->modalDescription(
                        fn(Term $record) =>
                        "This will permanently delete the folder and ALL media attached to it.\n\n" .
                        "Type DELETE to confirm."
                    )
                    ->form([
                        \Filament\Forms\Components\TextInput::make('confirm')
                            ->label('Type DELETE to confirm')
                            ->required()
                            ->rule('in:DELETE'),
                    ])
                    ->action(function (Term $record): void {
                        DB::transaction(function () use ($record) {
                            // deletes media files + variants + DB records (and child folders if any)
                            $record->deleteFolderAndItsMedia(recursive: true);
                        });

                        Notification::make()
                            ->title('Folder deleted (and media removed).')
                            ->success()
                            ->send();
                    }),
            ]);
    }
}