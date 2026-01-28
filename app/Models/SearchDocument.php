<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SearchDocument extends Model
{
    protected $fillable = [
        'entity_type',
        'entity_id',
        'title',
        'content',
        'slug',
        'url',
        'meta',
        'is_public',
        'published_at',
    ];

    protected $casts = [
        'meta' => 'array',
        'is_public' => 'bool',
        'published_at' => 'datetime',
    ];
}