<?php

namespace App\Filament\Resources\Posts\Pages;

use App\Filament\Resources\Posts\PostResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Artisan;

class ListPosts extends ListRecords
{
    protected static string $resource = PostResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('publishAssets')
                ->label('Publish Assets')
                ->icon('heroicon-o-arrow-up-tray')
                ->color('gray')
                ->requiresConfirmation()
                ->action(function () {
                    Artisan::call('cms:publish-assets', ['--clean' => true]);

                    Notification::make()
                        ->title('Assets published')
                        ->body(trim(Artisan::output()))
                        ->success()
                        ->send();
                }),

            CreateAction::make()
                ->label('Add Post')
                ->icon('heroicon-o-plus')
                ->color('info'),
        ];
    }

    protected function getDefaultTableSortColumn(): ?string
    {
        return 'updated_at';
    }

    protected function getDefaultTableSortDirection(): ?string
    {
        return 'desc';
    }
}