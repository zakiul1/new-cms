<?php

namespace App\Jobs;

use App\Models\Media;
use App\Models\MediaVariant;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

class GenerateMediaVariants implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public int $mediaId)
    {
    }

    public function handle(): void
    {
        $media = Media::query()->find($this->mediaId);
        if (! $media || ! $media->isImage()) {
            return;
        }

        $disk = $media->disk;
        $format = config('cms-media.variant_format', 'webp');
        $sizes = config('cms-media.image_variants', []);

        $sourcePath = $media->path();
        $sourceAbs = Storage::disk($disk)->path($sourcePath);

        if (! is_file($sourceAbs)) {
            return;
        }

        // Load image
        $img = @imagecreatefromstring(file_get_contents($sourceAbs));
        if (! $img) {
            return;
        }

        $srcW = imagesx($img);
        $srcH = imagesy($img);

        foreach ($sizes as $key => $maxWidth) {
            $maxWidth = (int) $maxWidth;
            if ($maxWidth <= 0) continue;

            [$newW, $newH] = $this->fitWidth($srcW, $srcH, $maxWidth);

            // If smaller than target, still create thumb for consistent UI
            $resized = imagecreatetruecolor($newW, $newH);

            // keep transparency
            imagealphablending($resized, false);
            imagesavealpha($resized, true);

            imagecopyresampled($resized, $img, 0, 0, 0, 0, $newW, $newH, $srcW, $srcH);

            $variantName = pathinfo($media->filename, PATHINFO_FILENAME) . "-{$key}.{$format}";
            $variantDir = $media->directory . '/variants';

            $tmp = tempnam(sys_get_temp_dir(), 'cmsv_');
            if ($tmp === false) continue;

            // write webp
            // quality 82 is a good default (balanced)
            imagewebp($resized, $tmp, 82);

            $stored = Storage::disk($disk)->putFileAs($variantDir, new \Illuminate\Http\File($tmp), $variantName);

            @unlink($tmp);
            imagedestroy($resized);

            if (! $stored) {
                continue;
            }

            $variantAbs = Storage::disk($disk)->path($variantDir . '/' . $variantName);

            MediaVariant::query()->updateOrCreate(
                ['media_id' => $media->id, 'key' => $key],
                [
                    'disk' => $disk,
                    'directory' => $variantDir,
                    'filename' => $variantName,
                    'mime_type' => 'image/webp',
                    'size' => @filesize($variantAbs) ?: 0,
                    'width' => $newW,
                    'height' => $newH,
                ]
            );
        }

        imagedestroy($img);
    }

    private function fitWidth(int $w, int $h, int $maxW): array
    {
        if ($w <= 0 || $h <= 0) return [$maxW, $maxW];

        if ($w <= $maxW) {
            return [$w, $h];
        }

        $ratio = $maxW / $w;

        return [$maxW, (int) round($h * $ratio)];
    }
}
