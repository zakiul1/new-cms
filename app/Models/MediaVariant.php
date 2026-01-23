<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class MediaVariant extends Model
{
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

    public function media(): BelongsTo
    {
        return $this->belongsTo(Media::class);
    }

    public function path(): string
    {
        return trim($this->directory, '/') . '/' . $this->filename;
    }

    public function url(): string
    {
        return Storage::disk($this->disk)->url($this->path());
    }
}
