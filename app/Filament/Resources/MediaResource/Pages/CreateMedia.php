<?php

namespace App\Filament\Resources\MediaResource\Pages;

use App\Cms\Media\MediaUploader;
use App\Filament\Resources\MediaResource;
use App\Models\Media;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Http\UploadedFile;

class CreateMedia extends CreateRecord
{
    protected static string $resource = MediaResource::class;

    /** @var array<int, UploadedFile> */
    protected array $remainingFiles = [];

    protected function handleRecordCreation(array $data): Media
    {
        $files = $data['files'] ?? [];

        if ($files instanceof UploadedFile) {
            $files = [$files];
        }

        if (! is_array($files) || count($files) === 0) {
            throw new \RuntimeException('No files were uploaded.');
        }

        // Normalize + keep only UploadedFile-like objects
        $files = array_values(array_filter($files, fn ($f) => $f instanceof UploadedFile));

        if (count($files) === 0) {
            throw new \RuntimeException('Uploaded files are invalid.');
        }

        // Upload first now (CreateRecord requires returning ONE model)
        $first = array_shift($files);

        // Store rest for afterCreate()
        $this->remainingFiles = $files;

        return app(MediaUploader::class)->upload($first);
    }

    protected function afterCreate(): void
    {
        if ($this->remainingFiles === []) {
            Notification::make()
                ->success()
                ->title('Uploaded 1 file')
                ->send();

            return;
        }

        $uploader = app(MediaUploader::class);

        $count = 1; // first already uploaded
        foreach ($this->remainingFiles as $file) {
            $uploader->upload($file);
            $count++;
        }

        Notification::make()
            ->success()
            ->title("Uploaded {$count} files")
            ->body('All files are now in the Media Library.')
            ->send();
    }

    // ✅ WP behavior: after upload go back to library list
    protected function getRedirectUrl(): string
    {
        return MediaResource::getUrl('index');
    }
}
