<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class MediaVariant extends Model
{
    protected $table = 'media_variants';

    protected $fillable = [
        'media_id',
        'key',
        'format',
        'disk',
        'directory',
        'filename',
        'mime_type',
        'size',
        'width',
        'height',
    ];

    protected $casts = [
        'media_id' => 'integer',
        'size' => 'integer',
        'width' => 'integer',
        'height' => 'integer',
    ];

    public function media(): BelongsTo
    {
        return $this->belongsTo(Media::class);
    }

    /**
     * Storage path relative to disk root.
     */
    public function path(): string
    {
        $dir = trim((string) $this->directory, '/');
        $file = ltrim((string) $this->filename, '/');

        return $dir === '' ? $file : "{$dir}/{$file}";
    }

    public function url(): string
    {
        $disk = (string) ($this->disk ?: config('cms-media.disk', 'public'));
        return Storage::disk($disk)->url($this->path());
    }

    // -------------------------
    // Helpers
    // -------------------------

    public function format(): string
    {
        return strtolower((string) ($this->format ?? ''));
    }

    public function isWebp(): bool
    {
        return $this->format() === 'webp';
    }

    public function isJpeg(): bool
    {
        return in_array($this->format(), ['jpeg', 'jpg'], true);
    }

    public function ext(): string
    {
        $f = $this->format();
        if ($f === 'jpeg')
            return 'jpg';
        return $f !== '' ? $f : strtolower((string) pathinfo((string) $this->filename, PATHINFO_EXTENSION));
    }

    // -------------------------
    // Scopes
    // -------------------------

    public function scopeForKey(Builder $query, string $key): Builder
    {
        return $query->where('key', $key);
    }

    public function scopeForFormat(Builder $query, string $format): Builder
    {
        return $query->where('format', strtolower($format));
    }
}