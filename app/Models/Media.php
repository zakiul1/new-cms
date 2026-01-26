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
        // If you keep the json column:
        'variants',
        'processed_at',
    ];

    protected $casts = [
        'meta' => 'array',
        // If you keep the json column:
        'variants' => 'array',
        'processed_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::deleting(function (self $media): void {
            // Delete files from disk (original + variants)
            app(MediaUploader::class)->deleteFiles($media);

            // Delete variant DB rows
            $media->variantRecords()->delete();
        });
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /**
     * ✅ IMPORTANT:
     * We renamed this because you also have a column called "variants".
     * So $media->variants would return the column value (null/array),
     * not the relationship collection.
     */
    public function variantRecords(): HasMany
    {
        return $this->hasMany(MediaVariant::class, 'media_id');
    }

    public function terms(): MorphToMany
    {
        return $this->morphToMany(Term::class, 'termable', 'termables')->withTimestamps();
    }

    public function path(): string
    {
        return trim($this->directory, '/') . '/' . ltrim($this->filename, '/');
    }

    public function url(): string
    {
        $disk = (string) ($this->disk ?: 'public');
        return Storage::disk($disk)->url($this->path());
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
        $variant = $this->variantRecords()->where('key', $key)->first();
        return $variant?->url();
    }

    public function thumbUrl(): ?string
    {
        // ✅ fallback to original if thumb missing
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

        $isFolderTerm = Term::query()
            ->where('taxonomy_id', $taxonomyId)
            ->where('id', $termId)
            ->exists();

        if (!$isFolderTerm) {
            return;
        }

        $this->clearFolderTerms();
        $this->terms()->syncWithoutDetaching([$termId]);
    }
}
