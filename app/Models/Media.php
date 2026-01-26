<?php

namespace App\Models;

use App\Cms\Media\MediaUploader;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Support\Facades\Storage;

class Media extends Model
{
    protected $table = 'media';

    protected $fillable = [
        'uploaded_by',
        'disk',
        'directory',
        'filename',
        'original_filename',
        'mime_type',
        'size',
        'width',
        'height',
        'sha1',
        'title',
        'alt',
        'caption',
        'description',
        'meta',
    ];

    protected $casts = [
        'meta' => 'array',
    ];

    /**
     * ✅ Automatically delete original + variants from disk when a Media record is deleted.
     * Works for: single delete, bulk delete, deleting from Edit page, etc.
     */
    protected static function booted(): void
    {
        static::deleting(function (self $media): void {
            // Delete files from disk (original + variants)
            app(MediaUploader::class)->deleteFiles($media);

            // Delete variant DB rows (in case you don't have ON DELETE CASCADE)
            $media->variants()->delete();
        });
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function variants(): HasMany
    {
        return $this->hasMany(MediaVariant::class);
    }

    public function terms(): MorphToMany
    {
        return $this->morphToMany(Term::class, 'termable', 'termables')->withTimestamps();
    }

    public function path(): string
    {
        return trim($this->directory, '/') . '/' . $this->filename;
    }

    public function url(): string
    {
        return Storage::disk($this->disk)->url($this->path());
    }

    public function isImage(): bool
    {
        return str_starts_with((string) $this->mime_type, 'image/');
    }

    public function isVideo(): bool
    {
        return str_starts_with((string) $this->mime_type, 'video/');
    }

    public function isPdf(): bool
    {
        return strtolower((string) $this->mime_type) === 'application/pdf';
    }

    public function variantUrl(string $key): ?string
    {
        $variant = $this->variants()->where('key', $key)->first();

        return $variant?->url();
    }

    public function thumbUrl(): ?string
    {
        // ✅ best UX: fallback to original while processing or if missing
        return $this->variantUrl('thumb') ?: ($this->isImage() ? $this->url() : null);
    }

    // -------------------------
    // Folder helpers (WP-like)
    // -------------------------

    protected function folderTaxonomyId(): ?int
    {
        return Taxonomy::query()->where('key', 'media_folder')->value('id');
    }

    public function clearFolderTerms(): void
    {
        $taxonomyId = $this->folderTaxonomyId();

        if (!$taxonomyId) {
            return;
        }

        $termIds = Term::query()
            ->where('taxonomy_id', $taxonomyId)
            ->pluck('id')
            ->all();

        if (count($termIds)) {
            $this->terms()->detach($termIds);
        }
    }

    public function syncFolderTerm(int $termId): void
    {
        $taxonomyId = $this->folderTaxonomyId();

        if (!$taxonomyId) {
            return;
        }

        // Ensure it's a folder term
        $isFolderTerm = Term::query()
            ->where('taxonomy_id', $taxonomyId)
            ->where('id', $termId)
            ->exists();

        if (!$isFolderTerm) {
            return;
        }

        // WP-like: keep only ONE folder term
        $this->clearFolderTerms();
        $this->terms()->syncWithoutDetaching([$termId]);
    }
}