<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class Media extends Model
{
    protected $table = 'media';

    protected $fillable = [
        'uploaded_by',
        'disk',
        'directory',
        'filename',
        'original_filename',
        'mime_type',
        'size',
        'width',
        'height',
        'sha1',
        'title',
        'alt',
        'caption',
        'description',
        'meta',
    ];

    protected $casts = [
        'meta' => 'array',
    ];

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function variants(): HasMany
    {
        return $this->hasMany(MediaVariant::class);
    }

    public function path(): string
    {
        return trim($this->directory, '/') . '/' . $this->filename;
    }

    public function url(): string
    {
        return Storage::disk($this->disk)->url($this->path());
    }

    public function isImage(): bool
    {
        return str_starts_with((string) $this->mime_type, 'image/');
    }

    public function variantUrl(string $key): ?string
    {
        $variant = $this->variants->firstWhere('key', $key);

        return $variant?->url();
    }

    public function thumbUrl(): ?string
    {
        return $this->variantUrl('thumb') ?? ($this->isImage() ? $this->url() : null);
    }
}
