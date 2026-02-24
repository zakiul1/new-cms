<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\DB;

class Post extends Model
{
    protected $fillable = [
        'type',
        'title',
        'slug',
        'excerpt',

        // ✅ virtual (editor) field
        'content_html',

        'content_json',
        'status',
        'published_at',
        'author_id',

        // ✅ legacy single featured image (kept for backward compatibility)
        'featured_media_id',

        'meta_json',
    ];

    protected $casts = [
        'content_json' => 'array',
        'meta_json' => 'array',
        'published_at' => 'datetime',
    ];

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function revisions(): HasMany
    {
        return $this->hasMany(PostRevision::class);
    }

    public function terms(): MorphToMany
    {
        return $this->morphToMany(Term::class, 'termable', 'termables')->withTimestamps();
    }

    public function categories(): MorphToMany
    {
        return $this->terms()->whereHas('taxonomy', fn($q) => $q->where('key', 'category'));
    }

    public function tags(): MorphToMany
    {
        return $this->terms()->whereHas('taxonomy', fn($q) => $q->where('key', 'tag'));
    }

    /**
     * ✅ Legacy single featured image (first featured image)
     */
    public function featuredMedia(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'featured_media_id');
    }

    /**
     * ✅ Pivot relation for all post/page media (role-based)
     */
    public function mediaPivot(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(
            \App\Models\Media::class,
            'post_media',
            'post_id',   // ✅ correct pivot FK
            'media_id'   // ✅ correct pivot related key
        )
            ->withPivot(['role', 'sort_order'])
            ->withTimestamps();
    }

    /**
     * ✅ New: multiple featured images (stored in post_media with role=featured)
     */
    public function featuredMediaPivot(): BelongsToMany
    {
        return $this->mediaPivot()
            ->wherePivot('role', 'featured')
            ->orderBy('post_media.sort_order');
    }

    /**
     * (Optional) Keep if you still use it somewhere.
     * Stored in post_media with role=gallery
     */
    public function galleryMedia(): BelongsToMany
    {
        return $this->mediaPivot()
            ->wherePivot('role', 'gallery')
            ->orderBy('post_media.sort_order');
    }

    /**
     * ✅ Virtual string field for WP Classic editor
     * Reads HTML from content_json['html']
     */
    public function getContentHtmlAttribute(): string
    {
        $json = $this->content_json;

        // tolerate legacy string
        if (is_string($json)) {
            $decoded = json_decode($json, true);
            $json = is_array($decoded) ? $decoded : [];
        }

        if (!is_array($json)) {
            $json = [];
        }

        $html = $json['html'] ?? '';

        return is_string($html) ? $html : '';
    }

    /**
     * ✅ Save HTML into content_json['html'] (same DB column)
     */
    public function setContentHtmlAttribute($value): void
    {
        $html = is_string($value) ? $value : '';

        $json = $this->content_json;

        // tolerate legacy string
        if (is_string($json)) {
            $decoded = json_decode($json, true);
            $json = is_array($decoded) ? $decoded : [];
        }

        if (!is_array($json)) {
            $json = [];
        }

        $json['html'] = $html;

        $this->attributes['content_json'] = json_encode(
            $json,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );
    }

    public function scopeFrontendVisible($query)
    {
        return $query->whereDoesntHave('categories', function ($q) {
            $q->where('terms.visibility', 'private');
        });
    }

    /**
     * ✅ Sync media for a specific role in post_media (featured/gallery/etc)
     * Keeps order via sort_order
     * Also filters invalid/deleted media IDs to prevent FK errors.
     */
    public function syncMediaRole(string $role, array $mediaIds): void
    {
        $role = trim($role);

        // normalize to ints, remove zeros
        $mediaIds = array_values(array_filter(array_map('intval', $mediaIds)));

        // ✅ keep only IDs that exist in media table (preserve given order)
        if ($mediaIds !== []) {
            $existing = Media::query()
                ->whereIn('id', $mediaIds)
                ->pluck('id')
                ->map(fn($id) => (int) $id)
                ->all();

            $set = array_flip($existing);

            $mediaIds = array_values(array_filter($mediaIds, fn($id) => isset($set[$id])));
        }

        DB::transaction(function () use ($role, $mediaIds) {
            DB::table('post_media')
                ->where('post_id', $this->id)
                ->where('role', $role)
                ->delete();

            foreach ($mediaIds as $i => $id) {
                $this->mediaPivot()->attach($id, [
                    'role' => $role,
                    'sort_order' => $i + 1,
                ]);
            }
        });
    }
}