<?php

namespace App\Filament\Resources\MediaResource\Pages;

use App\Cms\Media\MediaUploader;
use App\Filament\Resources\MediaResource;
use App\Models\Media;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Filament\Support\Enums\Width;
use Illuminate\Http\UploadedFile;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class CreateMedia extends CreateRecord
{
    protected static string $resource = MediaResource::class;

    /** @var array<int, TemporaryUploadedFile|UploadedFile> */
    protected array $remainingFiles = [];

    protected ?int $folderTermId = null;

    // ✅ Defaults applied to all uploaded files in this create action
    protected bool $attachmentPublicDefault = true;
    protected bool $attachmentIndexableDefault = true;

    public function getMaxContentWidth(): Width
    {
        return Width::Full; // ✅ full width like your Post create page
    }

    protected function handleRecordCreation(array $data): Media
    {
        // ✅ folder is optional (like WP)
        $this->folderTermId = isset($data['folder_term_id']) && filled($data['folder_term_id'])
            ? (int) $data['folder_term_id']
            : null;

        // ✅ Attachment defaults from the form (apply to ALL uploaded files)
        // If you didn't show these toggles on create, these will just stay true.
        $this->attachmentPublicDefault = array_key_exists('attachment_public', $data)
            ? (bool) $data['attachment_public']
            : true;

        $this->attachmentIndexableDefault = array_key_exists('attachment_indexable', $data)
            ? (bool) $data['attachment_indexable']
            : true;

        // Cleanup non-uploader keys
        unset($data['folder_term_id'], $data['attachment_public'], $data['attachment_indexable'], $data['slug']);

        $files = $data['files'] ?? [];

        // Filament may give: TemporaryUploadedFile[] (because storeFiles(false))
        if ($files instanceof TemporaryUploadedFile || $files instanceof UploadedFile) {
            $files = [$files];
        }

        if (!is_array($files) || $files === []) {
            throw new \RuntimeException('No files were uploaded.');
        }

        $files = array_values(array_filter(
            $files,
            fn($f) => $f instanceof TemporaryUploadedFile || $f instanceof UploadedFile
        ));

        if ($files === []) {
            throw new \RuntimeException('Uploaded files are invalid.');
        }

        $first = array_shift($files);
        $this->remainingFiles = $files;

        /** @var MediaUploader $uploader */
        $uploader = app(MediaUploader::class);

        $media = $uploader->upload($first);

        // ✅ Apply per-attachment defaults to the newly created record
        $this->applyAttachmentDefaults($media);

        // attach folder (optional)
        if ($this->folderTermId) {
            $media->terms()->syncWithoutDetaching([$this->folderTermId]);
        }

        return $media;
    }

    protected function afterCreate(): void
    {
        /** @var MediaUploader $uploader */
        $uploader = app(MediaUploader::class);

        $count = 1;

        foreach ($this->remainingFiles as $file) {
            $m = $uploader->upload($file);

            // ✅ Apply per-attachment defaults
            $this->applyAttachmentDefaults($m);

            if ($this->folderTermId) {
                $m->terms()->syncWithoutDetaching([$this->folderTermId]);
            }

            $count++;
        }

        Notification::make()
            ->success()
            ->title("Uploaded {$count} file" . ($count === 1 ? '' : 's'))
            ->body('All files are now in the Media Library.')
            ->send();
    }

    protected function getRedirectUrl(): string
    {
        return MediaResource::getUrl('index'); // ✅ WP feel
    }

    private function applyAttachmentDefaults(Media $media): void
    {
        // Only write if different (saves queries)
        $needsSave = false;

        if ((bool) $media->attachment_public !== $this->attachmentPublicDefault) {
            $media->attachment_public = $this->attachmentPublicDefault;
            $needsSave = true;
        }

        if ((bool) $media->attachment_indexable !== $this->attachmentIndexableDefault) {
            $media->attachment_indexable = $this->attachmentIndexableDefault;
            $needsSave = true;
        }

        if ($needsSave) {
            $media->save();
        }
    }
}