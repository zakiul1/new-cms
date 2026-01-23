<?php

namespace App\Cms\Media;

use App\Jobs\GenerateMediaVariants;
use App\Models\Media;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class MediaUploader
{
    public function upload(UploadedFile $file): Media
    {
        $disk = config('cms-media.disk', 'public');
        $baseDir = trim(config('cms-media.base_dir', 'media'), '/');

        $now = Carbon::now();
        $dir = "{$baseDir}/{$now->format('Y')}/{$now->format('m')}";

        $originalName = $file->getClientOriginalName();
        $mime = $file->getMimeType() ?: $file->getClientMimeType() ?: 'application/octet-stream';
        $size = (int) $file->getSize();

        $storedName = $this->safeUniqueFilename($file);

        // store original
        $path = $file->storeAs($dir, $storedName, $disk);

        $sha1 = @sha1_file($file->getRealPath()) ?: null;

        [$w, $h] = $this->imageSizeIfAny($file);

        $media = Media::create([
            'uploaded_by' => Auth::id(),
            'disk' => $disk,
            'directory' => $dir,
            'filename' => basename($path),
            'original_filename' => $originalName,
            'mime_type' => $mime,
            'size' => $size,
            'width' => $w,
            'height' => $h,
            'sha1' => $sha1,
            'title' => pathinfo($originalName, PATHINFO_FILENAME),
        ]);

        // generate variants async for images
        if ($media->isImage()) {
            GenerateMediaVariants::dispatch($media->id);
        }

        return $media;
    }

    private function safeUniqueFilename(UploadedFile $file): string
    {
        $name = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $name = Str::slug($name);
        $name = $name !== '' ? $name : 'file';

        $ext = strtolower($file->getClientOriginalExtension());
        $ext = $ext !== '' ? $ext : 'bin';

        return $name . '-' . Str::random(10) . '.' . $ext;
    }

    private function imageSizeIfAny(UploadedFile $file): array
    {
        $mime = $file->getMimeType() ?: '';

        if (! str_starts_with($mime, 'image/')) {
            return [null, null];
        }

        $info = @getimagesize($file->getRealPath());
        if (! is_array($info)) {
            return [null, null];
        }

        return [(int) $info[0], (int) $info[1]];
    }
}
