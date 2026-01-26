<?php

namespace App\Models;

use App\Cms\Media\MediaUploader;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class Term extends Model
{
    protected $fillable = [
        'taxonomy_id',
        'name',
        'slug',
        'description',
        'parent_id',
        'sort_order',
    ];

    protected static function booted(): void
    {
        static::creating(function (Term $term) {
            // ✅ auto slug (fixes "slug doesn't have default value")
            if (blank($term->slug)) {
                $term->slug = Str::slug((string) $term->name);
            }

            // ✅ unique slug inside same taxonomy
            $base = (string) $term->slug;
            $i = 2;

            while (
                static::query()
                    ->where('taxonomy_id', $term->taxonomy_id)
                    ->where('slug', $term->slug)
                    ->exists()
            ) {
                $term->slug = $base . '-' . $i;
                $i++;
            }
        });

        static::updating(function (Term $term) {
            // If slug is empty (or cleared), regenerate from name
            if (blank($term->slug)) {
                $term->slug = Str::slug((string) $term->name);
            }

            // Ensure uniqueness if slug changed
            if ($term->isDirty('slug')) {
                $base = (string) $term->slug;
                $i = 2;

                while (
                    static::query()
                        ->where('taxonomy_id', $term->taxonomy_id)
                        ->where('slug', $term->slug)
                        ->where('id', '!=', $term->id)
                        ->exists()
                ) {
                    $term->slug = $base . '-' . $i;
                    $i++;
                }
            }
        });
    }

    public function taxonomy(): BelongsTo
    {
        return $this->belongsTo(Taxonomy::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Term::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Term::class, 'parent_id')
            ->orderBy('sort_order')
            ->orderBy('name');
    }

    public function posts(): MorphToMany
    {
        return $this->morphedByMany(Post::class, 'termable', 'termables')->withTimestamps();
    }

    public function media(): MorphToMany
    {
        return $this->morphedByMany(Media::class, 'termable', 'termables')->withTimestamps();
    }

    /**
     * ✅ Delete this folder + ALL media inside it (and their files/variants).
     * - If $recursive=true, deletes child folders first.
     * - Uses MediaUploader::deleteFiles() so originals + variants are removed from disk.
     */
    public function deleteFolderAndItsMedia(bool $recursive = true): void
    {
        DB::transaction(function () use ($recursive) {
            $this->deleteFolderAndItsMediaInternal($recursive);
        });
    }

    /**
     * Internal recursive delete (no nested transactions).
     */
    protected function deleteFolderAndItsMediaInternal(bool $recursive): void
    {
        // 1) delete children folders first
        if ($recursive) {
            $this->loadMissing('children');

            foreach ($this->children as $child) {
                if ($child instanceof Term) {
                    $child->deleteFolderAndItsMediaInternal(true);
                }
            }
        }

        // 2) delete all media assigned to this folder
        $uploader = app(MediaUploader::class);

        $mediaItems = $this->media()->get();

        foreach ($mediaItems as $media) {
            if (!($media instanceof Media)) {
                continue;
            }

            // delete physical files (original + variants)
            $uploader->deleteFiles($media);

            // detach pivots to avoid orphan rows (if no FK cascade)
            $media->terms()->detach();

            // delete DB record
            $media->delete();
        }

        // 3) detach remaining pivots from this folder
        $this->media()->detach();
        $this->posts()->detach();

        // 4) delete folder term itself
        $this->delete();
    }
}