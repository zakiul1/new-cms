<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Taxonomy extends Model
{
    protected $fillable = ['key', 'label', 'hierarchical'];

    protected $casts = [
        'hierarchical' => 'bool',
    ];

    public function terms(): HasMany
    {
        return $this->hasMany(Term::class)->orderBy('sort_order')->orderBy('name');
    }

    // -------------------------
    // Helpers (WP-style)
    // -------------------------

    public static function byKey(string $key): ?self
    {
        return static::query()->where('key', $key)->first();
    }

    public static function idByKey(string $key): ?int
    {
        return static::query()->where('key', $key)->value('id');
    }

    public static function ensure(string $key, string $label, bool $hierarchical = true): self
    {
        return static::firstOrCreate(
            ['key' => $key],
            ['label' => $label, 'hierarchical' => $hierarchical],
        );
    }
}