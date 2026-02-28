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
     * - category_term_id: int|null          (preferred - single category like WP)
     * - category_term_ids: array<int|string> (legacy - if provided, first one is used)
     * - default_category_name: string (default: "Uncategorized")
     *
     * NOTE: folder_term_id is intentionally ignored (folder feature removed).
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

        // ✅ Dedupe (unchanged)
        if ((bool) config('cms-media.dedupe', true) && $sha1) {
            $existing = Media::query()
                ->where('sha1', $sha1)
                ->where('size', $sizeBytes)
                ->where('mime_type', $mime)
                ->first();

            if ($existing) {
                $existingDisk = (string) ($existing->disk ?: $disk);

                if (Storage::disk($existingDisk)->exists($existing->path())) {
                    // ✅ Apply category rules to deduped item too
                    $this->applyCategoryOptionsOnly($existing, $options);

                    return $existing;
                }
            }
        }

        // Ensure directory exists (safe no-op for most drivers)
        if (method_exists(Storage::disk($disk), 'makeDirectory')) {
            Storage::disk($disk)->makeDirectory($dir);
        }

        // ----------------------------
        // ✅ NEW: Normalize title + stored filename base using keyword list
        // ----------------------------
        $baseName = (string) (pathinfo($originalName, PATHINFO_FILENAME) ?: 'Untitled');
        $title = $this->makeNormalizedTitle($baseName);

        // Store using normalized base (numeric suffix for uniqueness)
        $ext = strtolower((string) $file->getClientOriginalExtension());
        $storedName = $this->safeUniqueFilenameFromBase($title, $ext, $disk, $dir);

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

            // ✅ Attachment fields
            'title' => $title,
            'slug' => $this->makeUniqueAttachmentSlug($title), // ✅ numeric style
            'attachment_public' => true,
            'attachment_indexable' => true,

            'processed_at' => null,
        ]);

        // ✅ Category only (folder removed)
        $this->applyCategoryOptionsOnly($media, $options);

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
        // ✅ Use normalized title for stored filename base (but keep existing Media title/slug)
        $baseName = (string) (pathinfo($originalName, PATHINFO_FILENAME) ?: 'Untitled');
        $normalizedForFile = $this->makeNormalizedTitle($baseName);
        $ext = strtolower((string) $file->getClientOriginalExtension());
        $storedName = $this->safeUniqueFilenameFromBase($normalizedForFile, $ext, $disk, $dir);

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

        // Update DB (do not change title/slug)
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
     * ✅ Category ONLY.
     * - Accepts category_term_id (single) OR category_term_ids (array)
     * - If none selected => attach/create "Uncategorized" in media_category taxonomy.
     *
     * @param array<string, mixed> $options
     */
    private function applyCategoryOptionsOnly(Media $media, array $options): void
    {
        // Folder removed: ignore any folder_term_id silently (keeps compatibility)
        // $options['folder_term_id'] is ignored on purpose.

        // Preferred: single category
        $single = $options['category_term_id'] ?? null;

        $categoryIds = [];

        if (filled($single) && is_numeric($single) && (int) $single > 0) {
            $categoryIds = [(int) $single];
        } else {
            // Legacy: array categories (we take FIRST like WP)
            $legacy = $options['category_term_ids'] ?? [];
            $legacy = is_array($legacy) ? $legacy : [];

            $legacyIds = collect($legacy)
                ->filter(fn($id) => is_numeric($id) && (int) $id > 0)
                ->map(fn($id) => (int) $id)
                ->unique()
                ->values()
                ->all();

            if (!empty($legacyIds)) {
                $categoryIds = [(int) $legacyIds[0]]; // WP-like single category
            }
        }

        if (!empty($categoryIds)) {
            // Your Media::syncCategoryTerms already validates taxonomy
            $media->syncCategoryTerms($categoryIds);
            return;
        }

        // If nothing selected -> attach default Uncategorized
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
     * ✅ Build a normalized human title from the filename base.
     * - Applies Str::title first (nice human formatting)
     * - Then enforces canonical keyword casing from config (USA, v-neck, etc.)
     */
    private function makeNormalizedTitle(string $rawBaseName): string
    {
        $rawBaseName = trim($rawBaseName);
        $rawBaseName = $rawBaseName !== '' ? $rawBaseName : 'Untitled';

        // Convert common separators to spaces, collapse spaces
        $text = str_replace(['-', '_'], ' ', $rawBaseName);
        $text = trim(preg_replace('/\s+/', ' ', $text) ?: '');

        // Humanize
        $text = $text !== '' ? Str::title(mb_strtolower($text)) : 'Untitled';

        // Enforce canonical keyword casing/forms
        return $this->applyCanonicalKeywords($text);
    }

    /**
     * ✅ Replace keyword variants with canonical forms from config.
     * Matching:
     * - case-insensitive
     * - treats separators (space, -, _) as equivalent while matching
     */
    private function applyCanonicalKeywords(string $text): string
    {
        $keywords = config('cms-media.filename_keyword_canonical', []);
        $seps = config('cms-media.filename_keyword_separators', [' ', '-', '_']);

        if (!is_array($keywords) || count($keywords) === 0) {
            return $text;
        }
        if (!is_array($seps) || count($seps) === 0) {
            $seps = [' ', '-', '_'];
        }

        // Normalize separators list into a regex class or alternation
        $sepPattern = '[' . preg_quote(implode('', $seps), '/') . '\s]+';

        // Map lower => canonical
        $canon = [];
        foreach ($keywords as $k) {
            $k = trim((string) $k);
            if ($k === '') {
                continue;
            }
            $canon[mb_strtolower($k)] = $k;
        }

        // Replace longest phrases first (so "in Bangladesh" wins over "in")
        uksort($canon, fn($a, $b) => mb_strlen($b) <=> mb_strlen($a));

        foreach ($canon as $lower => $canonical) {
            // Build flexible pattern:
            // - spaces inside keywords become "any separator(s)"
            // - hyphens/underscores inside keyword also treated as separators while matching
            $p = preg_quote($lower, '/');
            $p = str_replace(['\ ', '\-', '\_'], $sepPattern, $p);

            // Boundary-ish: avoid matching inside other alphanumerics
            $text = preg_replace(
                '/(?<![A-Za-z0-9])' . $p . '(?![A-Za-z0-9])/iu',
                $canonical,
                $text
            ) ?? $text;
        }

        // Clean spacing
        $text = trim(preg_replace('/\s+/', ' ', $text) ?: '');

        return $text !== '' ? $text : 'Untitled';
    }

    /**
     * ✅ Keep filename unique on disk (numeric suffix: file.png, file-2.png, file-3.png...)
     * Uses a provided base string (typically normalized title).
     */
    private function safeUniqueFilenameFromBase(
        string $baseForName,
        string $ext,
        string $disk,
        string $dir
    ): string {
        $name = Str::slug($baseForName, '-');
        $name = $name !== '' ? $name : 'file';

        $ext = strtolower(trim($ext));
        $ext = $ext !== '' ? $ext : 'bin';

        $base = $name;
        $filename = $base . '.' . $ext;

        $dir = trim($dir, '/');
        $i = 2;

        while (Storage::disk($disk)->exists($dir . '/' . $filename)) {
            $filename = $base . '-' . $i . '.' . $ext;
            $i++;
        }

        return $filename;
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