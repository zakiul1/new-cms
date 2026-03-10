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
use Illuminate\Support\Facades\Log;
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

        $disk = (string) ($media->disk ?: config('cms-media.disk', 'public'));
        $sizes = (array) config('cms-media.image_variants', []);

        if (empty($sizes)) {
            return;
        }

        $primary = strtolower((string) config('cms-media.variant_format', 'webp'));
        $alsoJpegFallback = (bool) config('cms-media.generate_jpeg_fallback', false);
        $alsoAvif = (bool) config('cms-media.generate_avif', false);

        $formats = [$primary];

        if ($alsoJpegFallback && !in_array($primary, ['jpeg', 'jpg'], true)) {
            $formats[] = 'jpeg';
        }

        if ($alsoAvif && $primary !== 'avif') {
            $formats[] = 'avif';
        }

        $formats = array_values(array_unique(array_filter($formats)));

        $sourcePath = $media->path();

        if (!Storage::disk($disk)->exists($sourcePath)) {
            return;
        }

        $bytes = Storage::disk($disk)->get($sourcePath);
        if (!is_string($bytes) || $bytes === '') {
            return;
        }

        $img = @imagecreatefromstring($bytes);
        if (!$img) {
            return;
        }

        $generatedAny = false;

        try {
            $srcW = imagesx($img);
            $srcH = imagesy($img);

            if ($srcW <= 0 || $srcH <= 0) {
                return;
            }

            foreach ($sizes as $key => $maxWidth) {
                $key = (string) $key;
                $maxWidth = (int) $maxWidth;

                if ($key === '' || $maxWidth <= 0) {
                    continue;
                }

                $allowUpscale = ($key === 'thumb');

                if (!$allowUpscale && $srcW <= $maxWidth) {
                    continue;
                }

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

                foreach ($formats as $format) {
                    $format = strtolower((string) $format);

                    if ($format === 'webp' && !function_exists('imagewebp')) {
                        Log::warning('WebP requested but GD imagewebp() not available', [
                            'media_id' => $media->id,
                        ]);
                        continue;
                    }

                    if ($format === 'avif' && !function_exists('imageavif')) {
                        Log::warning('AVIF requested but GD imageavif() not available', [
                            'media_id' => $media->id,
                        ]);
                        continue;
                    }

                    if (!$this->force) {
                        $exists = MediaVariant::query()
                            ->where('media_id', $media->id)
                            ->where('key', $key)
                            ->where('format', $format)
                            ->exists();

                        if ($exists) {
                            continue;
                        }
                    } else {
                        $existing = MediaVariant::query()
                            ->where('media_id', $media->id)
                            ->where('key', $key)
                            ->where('format', $format)
                            ->first();

                        if ($existing) {
                            Storage::disk((string) ($existing->disk ?: $disk))->delete($existing->path());
                            $existing->delete();
                        }
                    }

                    $ext = match ($format) {
                        'jpeg' => 'jpg',
                        default => $format,
                    };

                    $variantName = "{$baseName}-{$key}.{$ext}";

                    $tmp = tempnam(sys_get_temp_dir(), 'cmsv_');
                    if ($tmp === false) {
                        continue;
                    }

                    $quality = $this->qualityFor($format);

                    $written = $this->writeVariant($resized, $tmp, $format, $quality);
                    if (!$written) {
                        @unlink($tmp);
                        continue;
                    }

                    $stored = Storage::disk($disk)->putFileAs($variantDir, new File($tmp), $variantName);
                    @unlink($tmp);

                    if (!$stored) {
                        continue;
                    }

                    $storedPath = rtrim($variantDir, '/') . '/' . $variantName;
                    $storedSize = (int) (Storage::disk($disk)->size($storedPath) ?: 0);

                    MediaVariant::query()->updateOrCreate(
                        [
                            'media_id' => $media->id,
                            'key' => $key,
                            'format' => $format,
                        ],
                        [
                            'disk' => $disk,
                            'directory' => $variantDir,
                            'filename' => $variantName,
                            'mime_type' => $this->mimeForFormat($format),
                            'size' => $storedSize,
                            'width' => $newW,
                            'height' => $newH,
                        ]
                    );

                    $generatedAny = true;
                }

                imagedestroy($resized);
            }
        } finally {
            imagedestroy($img);
        }

        if ($generatedAny) {
            $media->forceFill(['processed_at' => now()])->save();
        }
    }

    private function writeVariant($gd, string $path, string $format, int $quality): bool
    {
        return match ($format) {
            'webp' => function_exists('imagewebp')
            ? (bool) @imagewebp($gd, $path, $this->clamp($quality, 1, 100))
            : false,

            'avif' => function_exists('imageavif')
            ? (bool) @imageavif($gd, $path, $this->clamp($quality, 1, 100))
            : false,

            'jpeg', 'jpg' => (bool) @imagejpeg($gd, $path, $this->clamp($quality, 1, 100)),

            'png' => (bool) @imagepng($gd, $path, 6),

            default => false,
        };
    }

    private function qualityFor(string $format): int
    {
        return match ($format) {
            'webp' => (int) (config('cms-media.quality.webp') ?? 82),
            'avif' => (int) (config('cms-media.quality.avif') ?? 45),
            'jpeg', 'jpg' => (int) (config('cms-media.quality.jpeg') ?? 85),
            'png' => (int) (config('cms-media.quality.png') ?? 90),
            default => 82,
        };
    }

    private function mimeForFormat(string $format): string
    {
        return match ($format) {
            'jpeg', 'jpg' => 'image/jpeg',
            'png' => 'image/png',
            'webp' => 'image/webp',
            'avif' => 'image/avif',
            default => 'application/octet-stream',
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

        $ratio = $maxW / $w;

        return [$maxW, (int) max(1, round($h * $ratio))];
    }

    private function clamp(int $v, int $min, int $max): int
    {
        return max($min, min($max, $v));
    }
}