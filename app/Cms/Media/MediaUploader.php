<?php

namespace App\Cms\Media;

use App\Jobs\GenerateMediaVariants;
use App\Models\Media;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class MediaUploader
{
    /**
     * Upload a file and return Media record.
     *
     * @param  UploadedFile|TemporaryUploadedFile  $file
     */
    public function upload(UploadedFile $file): Media
    {
        $disk = (string) config('cms-media.disk', 'public');
        $baseDir = trim((string) config('cms-media.base_dir', 'media'), '/');
        $maxMb = (int) config('cms-media.max_upload_mb', 50);

        // Basic size guard (same as UI maxSize)
        $sizeBytes = (int) ($file->getSize() ?: 0);
        if ($sizeBytes > ($maxMb * 1024 * 1024)) {
            throw new \RuntimeException("File is too large. Max allowed: {$maxMb} MB");
        }

        $now = Carbon::now();
        $dir = "{$baseDir}/{$now->format('Y')}/{$now->format('m')}";

        $originalName = (string) $file->getClientOriginalName();
        $mime = (string) ($file->getMimeType() ?: $file->getClientMimeType() ?: 'application/octet-stream');

        // Compute sha1 BEFORE storing (temp file exists now)
        $sha1 = $this->sha1OfUploadedFile($file);

        // Compute width/height for images
        [$w, $h] = $this->imageSizeIfAny($file, $mime);

        // ✅ Dedupe: if same file already exists, reuse record
        if ((bool) config('cms-media.dedupe', true) && $sha1) {
            $existing = Media::query()
                ->where('sha1', $sha1)
                ->where('size', $sizeBytes)
                ->first();

            // If record exists and file is present, reuse it
            if ($existing && Storage::disk((string) $existing->disk)->exists($existing->path())) {
                return $existing;
            }
        }

        // Store original
        $storedName = $this->safeUniqueFilename($file);
        $path = $file->storeAs($dir, $storedName, $disk);

        $media = Media::create([
            'uploaded_by' => Auth::id(),
            'disk' => $disk,
            'directory' => $dir,
            'filename' => basename((string) $path),
            'original_filename' => $originalName,
            'mime_type' => $mime,
            'size' => $sizeBytes,
            'width' => $w,
            'height' => $h,
            'sha1' => $sha1,
            'title' => (string) (pathinfo($originalName, PATHINFO_FILENAME) ?: 'Untitled'),
        ]);

        // Generate variants (images only)
        if ($media->isImage()) {
            $this->dispatchVariantsJob($media->id, false);
        }

        return $media;
    }

    /**
     * ✅ Replace the original file but keep the same Media record (WP-like).
     *
     * @param  UploadedFile|TemporaryUploadedFile  $file
     */
    public function replace(Media $media, UploadedFile $file): Media
    {
        $disk = (string) $media->disk;
        $dir = trim((string) $media->directory, '/');
        $maxMb = (int) config('cms-media.max_upload_mb', 50);

        $sizeBytes = (int) ($file->getSize() ?: 0);
        if ($sizeBytes > ($maxMb * 1024 * 1024)) {
            throw new \RuntimeException("File is too large. Max allowed: {$maxMb} MB");
        }

        $oldPath = $media->path();

        $originalName = (string) $file->getClientOriginalName();
        $mime = (string) ($file->getMimeType() ?: $file->getClientMimeType() ?: 'application/octet-stream');
        $sha1 = $this->sha1OfUploadedFile($file);
        [$w, $h] = $this->imageSizeIfAny($file, $mime);

        // Store new original first (safety)
        $storedName = $this->safeUniqueFilename($file);
        $path = $file->storeAs($dir, $storedName, $disk);

        // ✅ Remove old variants files + records (SAFE ALWAYS)
        $variants = $media->variants()->get();

        foreach ($variants as $variant) {
            $variantPath = trim((string) $variant->directory, '/') . '/' . (string) $variant->filename;
            Storage::disk((string) $variant->disk)->delete($variantPath);
        }

        $media->variants()->delete();

        // Remove old original AFTER new save succeeded
        Storage::disk($disk)->delete($oldPath);

        // Update DB
        $media->update([
            'filename' => basename((string) $path),
            'original_filename' => $originalName,
            'mime_type' => $mime,
            'size' => $sizeBytes,
            'width' => $w,
            'height' => $h,
            'sha1' => $sha1,
        ]);

        // Regenerate variants if image
        if ($media->isImage()) {
            $this->dispatchVariantsJob($media->id, true);
        }

        return $media->refresh();
    }


    /**
     * Optional helper: delete original + variants from disk.
     * You can call this before deleting the DB record.
     */
    public function deleteFiles(Media $media): void
    {
        $media->loadMissing('variants');

        // delete variants
        foreach ($media->variants as $variant) {
            $variantPath = trim((string) $variant->directory, '/') . '/' . (string) $variant->filename;
            Storage::disk((string) $variant->disk)->delete($variantPath);
        }

        // delete original
        Storage::disk((string) $media->disk)->delete($media->path());
    }

    private function dispatchVariantsJob(int $mediaId, bool $force): void
    {
        $queueEnabled = (bool) config('cms-media.queue.enabled', true);

        $job = GenerateMediaVariants::dispatch($mediaId, $force);

        // If queue enabled, respect configured connection/queue name.
        if ($queueEnabled) {
            $connection = (string) config('cms-media.queue.connection', config('queue.default'));
            $queue = (string) config('cms-media.queue.queue', 'media');

            $job->onConnection($connection)->onQueue($queue);
            return;
        }

        GenerateMediaVariants::dispatchSync($mediaId, $force);
    }

    private function safeUniqueFilename(UploadedFile $file): string
    {
        $name = Str::slug((string) pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME));
        $name = $name !== '' ? $name : 'file';

        $ext = strtolower((string) $file->getClientOriginalExtension());
        $ext = $ext !== '' ? $ext : 'bin';

        return $name . '-' . Str::random(10) . '.' . $ext;
    }

    private function sha1OfUploadedFile(UploadedFile $file): ?string
    {
        $realPath = $file->getRealPath();

        if (!is_string($realPath) || $realPath === '' || !is_file($realPath)) {
            return null;
        }

        $hash = @sha1_file($realPath);

        return is_string($hash) && $hash !== '' ? $hash : null;
    }

    private function imageSizeIfAny(UploadedFile $file, string $mime): array
    {
        if (!str_starts_with($mime, 'image/')) {
            return [null, null];
        }

        $realPath = $file->getRealPath();

        if (!is_string($realPath) || $realPath === '' || !is_file($realPath)) {
            return [null, null];
        }

        $info = @getimagesize($realPath);

        if (!is_array($info)) {
            return [null, null];
        }

        return [(int) $info[0], (int) $info[1]];
    }
}