<?php

namespace Plugins\MediaNameChanger\Jobs;

use App\Models\Media;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Plugins\MediaNameChanger\Support\MediaRenameService;
use Throwable;

class RenameMediaBatch implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * @param array<int>  $mediaIds
     * @param array<int,string> $namesByIndex
     */
    public function __construct(
        public string $batchKey,
        public array $mediaIds,
        public array $namesByIndex,
        public ?int $moveToCategoryId = null,
    ) {
    }

    public function handle(MediaRenameService $svc): void
    {
        $state = Cache::get($this->batchKey, []);
        $total = (int) ($state['total'] ?? count($this->mediaIds));

        Cache::put($this->batchKey, array_merge($state, [
            'status' => 'running',
            'total' => $total,
            'done' => (int) ($state['done'] ?? 0),
            'errors' => $state['errors'] ?? [],
        ]), now()->addHours(2));

        $done = (int) ($state['done'] ?? 0);
        $errors = is_array($state['errors'] ?? null) ? $state['errors'] : [];

        foreach ($this->mediaIds as $i => $mediaId) {
            $name = (string) ($this->namesByIndex[$i] ?? '');

            try {
                $media = Media::query()->find($mediaId);
                if (!$media) {
                    throw new \RuntimeException("Media not found: {$mediaId}");
                }

                $svc->renameOne($media, $name, $this->moveToCategoryId);
            } catch (Throwable $e) {
                $errors[] = "ID {$mediaId}: " . $e->getMessage();
            }

            $done++;

            Cache::put($this->batchKey, [
                'status' => 'running',
                'total' => $total,
                'done' => $done,
                'errors' => $errors,
            ], now()->addHours(2));
        }

        Cache::put($this->batchKey, [
            'status' => 'finished',
            'total' => $total,
            'done' => $done,
            'errors' => $errors,
        ], now()->addHours(2));
    }
}