<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MenuItem extends Model
{
    protected $fillable = [
        'menu_id',
        'parent_id',
        'label',
        'type',
        'url',
        'sort_order',
        'is_enabled',

        // Object-backed links (posts/terms/custom/dynamic)
        'object_type',   // post|term|custom|dynamic
        'object_id',
        'taxonomy_key',

        // Premium: advanced attributes
        'target',        // _blank
        'rel',           // "nofollow ugc sponsored"
        'css_class',
        'css_id',
        'icon',
        'description',

        // Premium: visibility rules
        'visibility',

        // Premium: mega menu config & other future meta
        'data',
    ];

    protected $casts = [
        'is_enabled' => 'bool',
        'visibility' => 'array',
        'data' => 'array',
    ];

    public function menu(): BelongsTo
    {
        return $this->belongsTo(Menu::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(MenuItem::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(MenuItem::class, 'parent_id')->orderBy('sort_order');
    }
}