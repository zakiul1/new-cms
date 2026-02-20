<?php

namespace Plugins\MediaNameChanger\Support;

use App\Jobs\GenerateMediaVariants;
use App\Models\Media;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Bus;
use RuntimeException;

class MediaRenameService
{
    /**
     * Rename ONE media item safely:
     * - rename file on disk
     * - update slug/title/alt/caption
     * - regenerate variants (force=true)
     */
    public function renameOne(Media $media, string $newDisplayName, ?int $moveToCategoryId = null): void
    {
        $newDisplayName = trim($newDisplayName);
        if ($newDisplayName === '') {
            throw new RuntimeException('Empty name.');
        }

        if (!$media->disk) {
            $media->disk = (string) config('cms-media.disk', 'public');
        }

        $disk = (string) $media->disk;

        // Keep extension from current filename
        $ext = strtolower((string) pathinfo((string) $media->filename, PATHINFO_EXTENSION));
        if ($ext === '') {
            // fallback from mime if needed
            $ext = $this->guessExtensionFromMime((string) $media->mime_type) ?: 'jpg';
        }

        $base = Str::slug(Str::limit($newDisplayName, 120, ''));
        $base = $base !== '' ? $base : 'media';

        // Ensure unique slug (WP-like base, base-2, base-3)
        $slug = $this->uniqueMediaSlug($base, $media->id);

        $newFilename = $slug . '.' . $ext;

        DB::transaction(function () use ($media, $disk, $newFilename, $slug, $newDisplayName, $moveToCategoryId) {
            $oldPath = $media->path();
            $newPath = trim((string) $media->directory, '/') . '/' . ltrim($newFilename, '/');

            if (!Storage::disk($disk)->exists($oldPath)) {
                throw new RuntimeException("Original file missing: {$oldPath}");
            }

            if ($oldPath !== $newPath && Storage::disk($disk)->exists($newPath)) {
                throw new RuntimeException("Target file already exists: {$newPath}");
            }

            // 1) move file
            if ($oldPath !== $newPath) {
                $ok = Storage::disk($disk)->move($oldPath, $newPath);
                if (!$ok) {
                    throw new RuntimeException('Failed to rename file in storage.');
                }
            }

            // 2) update DB fields
            $media->forceFill([
                'title' => $newDisplayName,
                'slug' => $slug,
                'alt' => $newDisplayName,
                'caption' => $newDisplayName,
                'filename' => $newFilename,
            ])->save();

            // 3) move category if requested
            if ($moveToCategoryId && $moveToCategoryId > 0) {
                $media->syncCategoryTerms([$moveToCategoryId]);
            }

            // 4) regenerate variants (force) so thumb/medium/large match new base name
            // dispatch sync to keep “error-free” results immediately
            Bus::dispatchSync(new GenerateMediaVariants($media->id, true));
        });
    }

    private function uniqueMediaSlug(string $base, int $ignoreMediaId): string
    {
        $slug = $base;
        $i = 2;

        while (
            Media::query()
                ->where('slug', $slug)
                ->where('id', '!=', $ignoreMediaId)
                ->exists()
        ) {
            $slug = $base . '-' . $i;
            $i++;
        }

        return $slug;
    }

    private function guessExtensionFromMime(string $mime): ?string
    {
        $mime = strtolower(trim($mime));

        return match ($mime) {
            'image/jpeg' => 'jpg',
            'image/jpg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'image/gif' => 'gif',
            'application/pdf' => 'pdf',
            default => null,
        };
    }
}