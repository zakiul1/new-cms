<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class MediaVariant extends Model
{
    protected $table = 'media_variants';

    protected $fillable = [
        'media_id',
        'key',
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

    public function path(): string
    {
        $dir = trim((string) $this->directory, '/');
        $file = ltrim((string) $this->filename, '/');

        return $dir === '' ? $file : "{$dir}/{$file}";
    }

   public function url(): string
{
    $disk = (string) ($this->disk ?: 'public');
    return Storage::disk($disk)->url($this->path());
}

}
