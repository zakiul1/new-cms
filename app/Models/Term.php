<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

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
        return $this->hasMany(Term::class, 'parent_id')->orderBy('sort_order')->orderBy('name');
    }

    public function posts(): MorphToMany
    {
        return $this->morphedByMany(Post::class, 'termable', 'termables')->withTimestamps();
    }

    public function media(): MorphToMany
    {
        return $this->morphedByMany(Media::class, 'termable', 'termables')->withTimestamps();
    }
}