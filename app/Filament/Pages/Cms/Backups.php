<?php

namespace App\Filament\Pages\Cms;

use App\Cms\Backup\CmsBackupService;
use App\Models\CmsBackup;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Storage;
use UnitEnum;

class Backups extends Page
{
    protected string $view = 'filament.pages.cms.backups';

    protected static string|UnitEnum|null $navigationGroup = 'Tools';
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-archive-box';
    protected static ?string $title = 'Backups';

    public function getBackupsProperty()
    {
        return CmsBackup::query()->latest('id')->limit(50)->get();
    }
    public function delete(int $id): void
    {
        $b = CmsBackup::query()->findOrFail($id);

        // delete zip first
        Storage::disk($b->disk)->delete($b->path);

        // delete db row
        $b->delete();

        Notification::make()
            ->title('Backup deleted')
            ->success()
            ->send();
    }


    protected function getHeaderActions(): array
    {
        return [
            Action::make('create')
                ->label('Create Backup')
                ->action(function (CmsBackupService $svc) {
                    $svc->create('Manual');
                    Notification::make()->title('Backup created')->success()->send();
                }),

            Action::make('prune')
                ->label('Prune Old')
                ->color('gray')
                ->requiresConfirmation()
                ->action(function (CmsBackupService $svc) {
                    $n = $svc->prune();
                    Notification::make()->title("Pruned {$n} backups")->success()->send();
                }),
        ];
    }

    public function download(int $id)
    {
        $b = CmsBackup::query()->findOrFail($id);

        // Stream download (works with local disk)
        $path = Storage::disk($b->disk)->path($b->path);
        return response()->download($path, basename($b->path));
    }

    public function restore(int $id): void
    {
        $b = CmsBackup::query()->findOrFail($id);

        app(CmsBackupService::class)->restore($b, true);

        Notification::make()
            ->title('Backup restored')
            ->body('Content + media were restored successfully.')
            ->success()
            ->send();
    }
}