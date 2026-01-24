<?php

namespace App\Jobs;

use App\Models\Media;
use App\Models\MediaVariant;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\File;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

class GenerateMediaVariants implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public int $mediaId,
        public bool $force = false,
    ) {
    }

    public function handle(): void
    {
        $media = Media::query()->find($this->mediaId);

        if (!$media || !$media->isImage()) {
            return;
        }

        $disk = (string) $media->disk;
        $format = strtolower((string) config('cms-media.variant_format', 'webp')); // webp|avif|jpeg|png
        $sizes = (array) config('cms-media.image_variants', []);

        $quality = (int) (config("cms-media.quality.{$format}") ?? 82);
        if (in_array($format, ['jpg', 'jpeg'], true)) {
            $quality = (int) (config('cms-media.quality.jpeg') ?? 85);
        }

        $sourcePath = $media->path();
        $sourceAbs = Storage::disk($disk)->path($sourcePath);

        if (!is_file($sourceAbs) || !is_readable($sourceAbs)) {
            return;
        }

        $bytes = @file_get_contents($sourceAbs);
        if (!is_string($bytes) || $bytes === '') {
            return;
        }

        $img = @imagecreatefromstring($bytes);
        if (!$img) {
            return;
        }

        try {
            $srcW = imagesx($img);
            $srcH = imagesy($img);

            if ($srcW <= 0 || $srcH <= 0) {
                return;
            }

            foreach ($sizes as $key => $maxWidth) {
                $key = (string) $key;
                $maxWidth = (int) $maxWidth;

                if ($maxWidth <= 0) {
                    continue;
                }

                if (!$this->force) {
                    if (MediaVariant::query()->where('media_id', $media->id)->where('key', $key)->exists()) {
                        continue;
                    }
                } else {
                    // delete existing variant record + file (force regenerate)
                    $existing = MediaVariant::query()
                        ->where('media_id', $media->id)
                        ->where('key', $key)
                        ->first();

                    if ($existing) {
                        Storage::disk($existing->disk)->delete(trim($existing->directory, '/') . '/' . $existing->filename);
                        $existing->delete();
                    }
                }

                $allowUpscale = ($key === 'thumb');
                [$newW, $newH] = $this->fitWidth($srcW, $srcH, $maxWidth, $allowUpscale);

                if ($newW <= 0 || $newH <= 0) {
                    continue;
                }

                $resized = imagecreatetruecolor($newW, $newH);
                if (!$resized) {
                    continue;
                }

                imagealphablending($resized, false);
                imagesavealpha($resized, true);

                imagecopyresampled(
                    $resized,
                    $img,
                    0,
                    0,
                    0,
                    0,
                    $newW,
                    $newH,
                    $srcW,
                    $srcH
                );

                $variantDir = rtrim((string) $media->directory, '/') . '/variants';
                $baseName = pathinfo((string) $media->filename, PATHINFO_FILENAME);
                $variantName = "{$baseName}-{$key}.{$format}";

                $tmp = tempnam(sys_get_temp_dir(), 'cmsv_');
                if ($tmp === false) {
                    imagedestroy($resized);
                    continue;
                }

                $written = $this->writeVariant($resized, $tmp, $format, $quality);

                imagedestroy($resized);

                if (!$written) {
                    @unlink($tmp);
                    continue;
                }

                $stored = Storage::disk($disk)->putFileAs($variantDir, new File($tmp), $variantName);

                @unlink($tmp);

                if (!$stored) {
                    continue;
                }

                $variantAbs = Storage::disk($disk)->path($variantDir . '/' . $variantName);

                MediaVariant::query()->updateOrCreate(
                    ['media_id' => $media->id, 'key' => $key],
                    [
                        'disk' => $disk,
                        'directory' => $variantDir,
                        'filename' => $variantName,
                        'mime_type' => $this->mimeForFormat($format),
                        'size' => @filesize($variantAbs) ?: 0,
                        'width' => $newW,
                        'height' => $newH,
                    ]
                );
            }
        } finally {
            imagedestroy($img);
        }
    }

    private function writeVariant($gd, string $path, string $format, int $quality): bool
    {
        $format = strtolower($format);

        return match ($format) {
            'webp' => function_exists('imagewebp') ? (bool) @imagewebp($gd, $path, $this->clamp($quality, 1, 100)) : false,
            'jpg', 'jpeg' => (bool) @imagejpeg($gd, $path, $this->clamp($quality, 1, 100)),
            'png' => (bool) @imagepng($gd, $path, 6),
            default => function_exists('imagewebp') ? (bool) @imagewebp($gd, $path, $this->clamp($quality, 1, 100)) : false,
        };
    }

    private function mimeForFormat(string $format): string
    {
        return match (strtolower($format)) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            default => 'image/webp',
        };
    }

    private function fitWidth(int $w, int $h, int $maxW, bool $allowUpscale): array
    {
        if ($w <= 0 || $h <= 0 || $maxW <= 0) {
            return [0, 0];
        }

        if (!$allowUpscale && $w <= $maxW) {
            return [$w, $h];
        }

        if ($allowUpscale && $w <= $maxW) {
            return [$w, $h];
        }

        $ratio = $maxW / $w;

        return [$maxW, (int) max(1, round($h * $ratio))];
    }

    private function clamp(int $v, int $min, int $max): int
    {
        return max($min, min($max, $v));
    }
}