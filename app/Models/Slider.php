<?php

namespace App\Models;
use Illuminate\Support\Str;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Slider extends Model
{
    protected $fillable = [
        'name',
        'key',
        'is_active',
        'settings_json',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'settings_json' => 'array',
    ];

    public function slides(): HasMany
    {
        return $this->hasMany(Slide::class)->orderBy('sort_order');
    }

    public function activeSlides(): HasMany
    {
        return $this->slides()->where('is_active', true);
    }
    
protected static function booted(): void
{
    static::saving(function (self $slider) {
        $base = $slider->key ?: $slider->name;
        $base = Str::slug((string) $base);
        $base = $base !== '' ? $base : 'slider';

        $key = $base;
        $i = 2;

        while (
            self::query()
                ->where('key', $key)
                ->when($slider->exists, fn ($q) => $q->whereKeyNot($slider->getKey()))
                ->exists()
        ) {
            $key = $base . '-' . $i;
            $i++;
        }

        $slider->key = $key;
    });
}
}
