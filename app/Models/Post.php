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
        'content_json',
        'status',
        'published_at',
        'author_id',
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

    public function featuredMedia(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'featured_media_id');
    }

    /**
     * ✅ Base pivot relation (WRITE HERE)
     */
    public function mediaPivot(): BelongsToMany
    {
        return $this->belongsToMany(Media::class, 'post_media')
            ->withPivot(['role', 'sort_order'])
            ->withTimestamps();
    }

    /**
     * ✅ Read relations (OK)
     */
    public function productMedia(): BelongsToMany
    {
        return $this->mediaPivot()
            ->wherePivot('role', 'product')
            ->orderBy('post_media.sort_order');
    }

    public function galleryMedia(): BelongsToMany
    {
        return $this->mediaPivot()
            ->wherePivot('role', 'gallery')
            ->orderBy('post_media.sort_order');
    }

    /**
     * ✅ Sync media for a role (product/gallery) with sort_order
     */
    public function syncMediaRole(string $role, array $mediaIds): void
    {
        $role = trim($role);
        $mediaIds = array_values(array_filter(array_map('intval', $mediaIds)));

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