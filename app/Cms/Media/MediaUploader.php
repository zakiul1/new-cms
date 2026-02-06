<?php

namespace App\Cms\Media;

use App\Jobs\GenerateMediaVariants;
use App\Models\Media;
use App\Models\Taxonomy;
use App\Models\Term;
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
     * Supported $options:
     * - folder_term_id: int|null
     * - category_term_ids: array<int|string>
     * - default_category_name: string (default: "Uncategorized")
     *
     * @param  UploadedFile|TemporaryUploadedFile  $file
     * @param  array<string, mixed> $options
     */
    public function upload(UploadedFile|TemporaryUploadedFile $file, array $options = []): Media
    {
        $disk = (string) config('cms-media.disk', 'public');
        $baseDir = trim((string) config('cms-media.base_dir', 'media'), '/');
        $maxMb = (int) config('cms-media.max_upload_mb', 50);

        $sizeBytes = (int) ($file->getSize() ?: 0);
        if ($sizeBytes > ($maxMb * 1024 * 1024)) {
            throw new \RuntimeException("File is too large. Max allowed: {$maxMb} MB");
        }

        $now = Carbon::now();
        $dir = "{$baseDir}/{$now->format('Y')}/{$now->format('m')}";

        $originalName = (string) $file->getClientOriginalName();
        $mime = (string) ($file->getMimeType() ?: $file->getClientMimeType() ?: 'application/octet-stream');

        $sha1 = $this->sha1OfUploadedFile($file);
        [$w, $h] = $this->imageSizeIfAny($file, $mime);

        // ✅ Dedupe
        if ((bool) config('cms-media.dedupe', true) && $sha1) {
            $existing = Media::query()
                ->where('sha1', $sha1)
                ->where('size', $sizeBytes)
                ->where('mime_type', $mime)
                ->first();

            if ($existing) {
                $existingDisk = (string) ($existing->disk ?: $disk);

                if (Storage::disk($existingDisk)->exists($existing->path())) {
                    // ✅ Ensure taxonomy attachments if needed (optional)
                    $this->applyFolderAndCategoryOptions($existing, $options);

                    return $existing;
                }
            }
        }

        // Ensure directory exists (safe no-op for most drivers)
        if (method_exists(Storage::disk($disk), 'makeDirectory')) {
            Storage::disk($disk)->makeDirectory($dir);
        }

        $storedName = $this->safeUniqueFilename($file);
        $path = $file->storeAs($dir, $storedName, $disk);

        $baseName = (string) (pathinfo($originalName, PATHINFO_FILENAME) ?: 'Untitled');

        // Convert dashes/underscores to spaces, collapse spaces, Title Case (optional)
        $title = trim(preg_replace('/\s+/', ' ', str_replace(['-', '_'], ' ', $baseName)) ?: '');
        $title = $title !== '' ? Str::title($title) : 'Untitled';

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

            // ✅ Attachment fields
            'title' => $title,
            'slug' => $this->makeUniqueAttachmentSlug($title), // ✅ now numeric style
            'attachment_public' => true,
            'attachment_indexable' => true,

            'processed_at' => null,
        ]);

        // ✅ Apply folder + category rules
        $this->applyFolderAndCategoryOptions($media, $options);

        if ($media->isImage()) {
            $this->dispatchVariantsJob($media->id, false);
        }

        return $media;
    }

    /**
     * Replace original file but keep same Media record (WP-like)
     *
     * @param  UploadedFile|TemporaryUploadedFile  $file
     */
    public function replace(Media $media, UploadedFile|TemporaryUploadedFile $file): Media
    {
        $disk = (string) ($media->disk ?: config('cms-media.disk', 'public'));
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

        if (method_exists(Storage::disk($disk), 'makeDirectory')) {
            Storage::disk($disk)->makeDirectory($dir);
        }

        // Store new original first
        $storedName = $this->safeUniqueFilename($file);
        $path = $file->storeAs($dir, $storedName, $disk);

        // Remove old variant files + records
        $variantRows = $media->variantRecords()->get();

        foreach ($variantRows as $variant) {
            $variantDisk = (string) ($variant->disk ?: $disk);
            Storage::disk($variantDisk)->delete($variant->path());
        }

        // Delete rows
        $media->variantRecords()->delete();

        // Remove old original AFTER new stored
        Storage::disk($disk)->delete($oldPath);

        // Ensure slug exists for older records (do NOT change existing slug)
        $titleForSlug = (string) ($media->title ?: pathinfo($originalName, PATHINFO_FILENAME) ?: 'Untitled');
        $slug = $media->slug ?: $this->makeUniqueAttachmentSlug($titleForSlug);

        // Update DB
        $media->update([
            'filename' => basename((string) $path),
            'original_filename' => $originalName,
            'mime_type' => $mime,
            'size' => $sizeBytes,
            'width' => $w,
            'height' => $h,
            'sha1' => $sha1,
            'processed_at' => null,

            // ✅ keep same slug; only backfill if missing
            'slug' => $slug,

            // ✅ ensure defaults exist (in case older record had nulls)
            'attachment_public' => $media->attachment_public ?? true,
            'attachment_indexable' => $media->attachment_indexable ?? true,
        ]);

        if ($media->isImage()) {
            $this->dispatchVariantsJob($media->id, true);
        }

        return $media->refresh();
    }

    /**
     * Delete original + variants from disk.
     */
    public function deleteFiles(Media $media): void
    {
        $disk = (string) ($media->disk ?: config('cms-media.disk', 'public'));

        $variantRows = $media->variantRecords()->get();
        foreach ($variantRows as $variant) {
            $variantDisk = (string) ($variant->disk ?: $disk);
            Storage::disk($variantDisk)->delete($variant->path());
        }

        Storage::disk($disk)->delete($media->path());
    }

    /**
     * ✅ Apply folder + categories.
     * If category_term_ids empty => attach "Uncategorized" in media_category taxonomy.
     *
     * @param array<string, mixed> $options
     */
    private function applyFolderAndCategoryOptions(Media $media, array $options): void
    {
        // -------------------------
        // Folder (optional)
        // -------------------------
        $folderTermId = $options['folder_term_id'] ?? null;

        if (filled($folderTermId) && is_numeric($folderTermId)) {
            $folderTermId = (int) $folderTermId;

            // Attach folder like your existing create flow
            $media->terms()->syncWithoutDetaching([$folderTermId]);
        }

        // -------------------------
        // Categories (optional, but default to Uncategorized)
        // -------------------------
        $categoryIds = $options['category_term_ids'] ?? [];
        $categoryIds = is_array($categoryIds) ? $categoryIds : [];

        $categoryIds = collect($categoryIds)
            ->filter(fn($id) => is_numeric($id) && (int) $id > 0)
            ->map(fn($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        if (!empty($categoryIds)) {
            // Your Media::syncCategoryTerms already validates taxonomy
            $media->syncCategoryTerms($categoryIds);
            return;
        }

        // If nothing selected -> attach default Uncategorized (taxonomy: media_category)
        $defaultName = (string) ($options['default_category_name'] ?? 'Uncategorized');
        $this->attachDefaultMediaCategory($media, $defaultName);
    }

    /**
     * Attach (or create) the "Uncategorized" term for media_category taxonomy
     */
    private function attachDefaultMediaCategory(Media $media, string $name = 'Uncategorized'): void
    {
        $taxonomy = Taxonomy::firstOrCreate(
            ['key' => 'media_category'],
            ['label' => 'Media Categories', 'hierarchical' => true],
        );

        $slugBase = Str::slug($name);
        $slugBase = $slugBase !== '' ? $slugBase : 'uncategorized';

        $term = Term::query()
            ->where('taxonomy_id', $taxonomy->id)
            ->where(function ($q) use ($slugBase, $name) {
                $q->where('slug', $slugBase)->orWhere('name', $name);
            })
            ->first();

        if (!$term) {
            $slug = $slugBase;
            $i = 2;
            while (Term::where('taxonomy_id', $taxonomy->id)->where('slug', $slug)->exists()) {
                $slug = $slugBase . '-' . $i;
                $i++;
            }

            $term = Term::create([
                'taxonomy_id' => $taxonomy->id,
                'name' => $name,
                'slug' => $slug,
                'parent_id' => null,
            ]);
        }

        // Replace only media_category taxonomy to ensure it's categorized
        $media->syncCategoryTerms([$term->id]);
    }

    /**
     * Correct queue dispatch: set connection/queue BEFORE dispatching.
     */
    private function dispatchVariantsJob(int $mediaId, bool $force): void
    {
        $queueEnabled = (bool) config('cms-media.queue.enabled', true);

        if ($queueEnabled) {
            $connection = (string) config('cms-media.queue.connection', config('queue.default'));
            $queue = (string) config('cms-media.queue.queue', 'media');

            GenerateMediaVariants::dispatch($mediaId, $force)
                ->onConnection($connection)
                ->onQueue($queue);

            return;
        }

        GenerateMediaVariants::dispatchSync($mediaId, $force);
    }

    /**
     * Keep filename unique on disk (recommended).
     * If you want numeric filenames too, tell me and I'll provide that version.
     */
    private function safeUniqueFilename(UploadedFile|TemporaryUploadedFile $file): string
    {
        $name = Str::slug((string) pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME));
        $name = $name !== '' ? $name : 'file';

        $ext = strtolower((string) $file->getClientOriginalExtension());
        $ext = $ext !== '' ? $ext : 'bin';

        return $name . '-' . Str::random(10) . '.' . $ext;
    }

    /**
     * ✅ Make attachment slug like posts/pages: base, base-2, base-3...
     */
    private function makeUniqueAttachmentSlug(string $title): string
    {
        $name = pathinfo($title, PATHINFO_FILENAME);
        $base = Str::slug(Str::limit($name, 120, ''));
        $base = $base !== '' ? $base : 'attachment';

        $slug = $base;
        $i = 2;

        while (Media::query()->where('slug', $slug)->exists()) {
            $slug = $base . '-' . $i;
            $i++;
        }

        return $slug;
    }

    private function sha1OfUploadedFile(UploadedFile|TemporaryUploadedFile $file): ?string
    {
        $realPath = $file->getRealPath();

        if (!is_string($realPath) || $realPath === '' || !is_file($realPath)) {
            return null;
        }

        $hash = @sha1_file($realPath);

        return is_string($hash) && $hash !== '' ? $hash : null;
    }

    private function imageSizeIfAny(UploadedFile|TemporaryUploadedFile $file, string $mime): array
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
//update