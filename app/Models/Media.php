<?php

namespace App\Models;

use App\Cms\Media\MediaUploader;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

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

        // ✅ attachment page fields
        'slug',
        'attachment_public',
        'attachment_indexable',

        'alt',
        'caption',
        'description',
        'meta',
        'processed_at',
    ];

    protected $casts = [
        'meta' => 'array',
        'processed_at' => 'datetime',

        // ✅ IMPORTANT: Filament toggles + query filters need real booleans
        'attachment_public' => 'bool',
        'attachment_indexable' => 'bool',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $media): void {
            // Optional safety: if slug was not set (older code paths), generate one.
            // Your MediaUploader already sets slug, so this is only a fallback.
            if (!filled($media->slug)) {
                $base = Str::slug(Str::limit((string) ($media->title ?: $media->original_filename ?: 'attachment'), 120, ''));
                $base = $base !== '' ? $base : 'attachment';

                // ✅ WP-style numeric uniqueness: base, base-2, base-3...
                $slug = $base;
                $i = 2;

                while (self::query()->where('slug', $slug)->exists()) {
                    $slug = $base . '-' . $i;
                    $i++;
                }

                $media->slug = $slug;
            }

            // Defaults if not set (migration defaults also cover this)
            if (!isset($media->attachment_public)) {
                $media->attachment_public = true;
            }
            if (!isset($media->attachment_indexable)) {
                $media->attachment_indexable = true;
            }
        });

        static::deleting(function (self $media): void {
            // Delete physical files (original + variants)
            app(MediaUploader::class)->deleteFiles($media);

            // DB variant rows are removed by FK cascade (media_variants.media_id)
        });
    }

    public function posts()
    {
        return $this->belongsToMany(Post::class, 'post_media')
            ->withPivot(['role', 'sort_order'])
            ->withTimestamps();
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

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
        return trim((string) $this->directory, '/') . '/' . ltrim((string) $this->filename, '/');
    }

    public function url(): string
    {
        $disk = (string) ($this->disk ?: config('cms-media.disk', 'public'));
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

    /**
     * Get variant URL with preferred format (webp) and fallback (jpeg).
     *
     * @param string      $key           thumb|medium|large
     * @param string|null $preferFormat  webp|jpeg|png|avif (default from config)
     */
    public function variantUrl(string $key, ?string $preferFormat = null): ?string
    {
        $preferFormat ??= (string) config('cms-media.variant_format', 'webp');
        $preferFormat = strtolower($preferFormat);

        $fallback = match ($preferFormat) {
            'webp' => 'jpeg',
            'jpeg', 'jpg' => 'webp',
            default => 'jpeg',
        };

        // Prefer using loaded relationship (no extra queries in grids)
        $this->loadMissing('variantRecords');

        $variants = $this->variantRecords;

        // 1) preferred format
        $v = $variants->firstWhere(
            fn($x) =>
            (string) ($x->key ?? '') === $key
            && strtolower((string) ($x->format ?? '')) === $preferFormat
        );

        // 2) fallback format
        if (!$v) {
            $v = $variants->firstWhere(
                fn($x) =>
                (string) ($x->key ?? '') === $key
                && strtolower((string) ($x->format ?? '')) === $fallback
            );
        }

        // 3) any format (for old data or partial rows)
        if (!$v) {
            $v = $variants->firstWhere('key', $key);
        }

        return $v?->url();
    }

    public function thumbUrl(?string $preferFormat = null): ?string
    {
        return $this->variantUrl('thumb', $preferFormat) ?: ($this->isImage() ? $this->url() : null);
    }

    // -------------------------
    // Folder helpers (WP-like)
    // taxonomy key: media_folder
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

    // -------------------------
    // ✅ Media Category helpers
    // taxonomy key: media_category
    // -------------------------

    protected function categoryTaxonomyId(): ?int
    {
        return Taxonomy::query()->where('key', 'media_category')->value('id');
    }

    /**
     * Media categories as terms (only taxonomy: media_category)
     */
    public function categories(): MorphToMany
    {
        $taxonomyId = $this->categoryTaxonomyId();

        // Always return a MorphToMany (type-safe)
        $rel = $this->terms();

        if (!$taxonomyId) {
            // force empty
            return $rel->whereRaw('0=1');
        }

        return $rel
            ->where('terms.taxonomy_id', $taxonomyId)
            ->orderBy('terms.name');
    }
    public function scopeFrontendVisible($query)
    {
        // Only public attachment items
        $query->where('attachment_public', true);

        // Exclude any media that has a PRIVATE media_category term
        $taxonomyId = Taxonomy::query()->where('key', 'media_category')->value('id');

        if ($taxonomyId) {
            $query->whereDoesntHave('terms', function ($q) use ($taxonomyId) {
                $q->where('terms.taxonomy_id', $taxonomyId)
                    ->where('terms.visibility', 'private');
            });
        }

        return $query;
    }

    /**
     * Remove only media_category terms from this media.
     */
    public function clearCategoryTerms(): void
    {
        $taxonomyId = $this->categoryTaxonomyId();
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

    /**
     * Sync media categories (multiple) safely.
     * If you pass an empty array, it leaves categories empty (unless you want auto-uncategorized behavior elsewhere).
     *
     * @param array<int|string> $termIds
     */
    public function syncCategoryTerms(array $termIds): void
    {
        $taxonomyId = $this->categoryTaxonomyId();
        if (!$taxonomyId) {
            return;
        }

        $termIds = collect($termIds)
            ->filter(fn($id) => is_numeric($id) && (int) $id > 0)
            ->map(fn($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        // Ensure provided terms belong to media_category taxonomy
        if (!empty($termIds)) {
            $termIds = Term::query()
                ->where('taxonomy_id', $taxonomyId)
                ->whereIn('id', $termIds)
                ->pluck('id')
                ->all();
        }

        // Replace only this taxonomy
        $this->clearCategoryTerms();

        if (!empty($termIds)) {
            $this->terms()->syncWithoutDetaching($termIds);
        }
    }
}