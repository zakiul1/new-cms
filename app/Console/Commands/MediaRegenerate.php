<?php

namespace App\Console\Commands;

use App\Jobs\GenerateMediaVariants;
use App\Models\Media;
use Illuminate\Console\Command;

class MediaRegenerate extends Command
{
    protected $signature = 'media:regenerate
        {--force : Delete existing variants and regenerate}
        {--queue : Force queue dispatch even if cms-media.queue.enabled is false}
        {--disk= : Only regenerate media on a specific disk (e.g. public)}
        {--only= : Comma-separated variant keys to target (thumb,medium,large). If omitted, uses config cms-media.image_variants}
        {--chunk=200 : Chunk size for scanning media records}';

    protected $description = 'Regenerate responsive image variants for all image media (WP-like regenerate thumbnails).';

    public function handle(): int
    {
        $force = (bool) $this->option('force');
        $forceQueue = (bool) $this->option('queue');
        $diskFilter = $this->option('disk');

        $only = $this->option('only');
        $onlyKeys = null;

        if (is_string($only) && trim($only) !== '') {
            $onlyKeys = array_values(array_filter(array_map('trim', explode(',', $only))));
        }

        $chunk = (int) $this->option('chunk');
        if ($chunk <= 0) {
            $chunk = 200;
        }

        $queueEnabled = $forceQueue || (bool) config('cms-media.queue.enabled', true);
        $connection = (string) config('cms-media.queue.connection', config('queue.default'));
        $queue = (string) config('cms-media.queue.queue', 'media');

        $query = Media::query()
            ->where('mime_type', 'like', 'image/%');

        if (is_string($diskFilter) && $diskFilter !== '') {
            $query->where('disk', $diskFilter);
        }

        $total = (clone $query)->count();

        if ($total === 0) {
            $this->info('No image media found.');
            return self::SUCCESS;
        }

        $this->info(
            "Found {$total} images. Regenerating variants"
            . ($force ? ' (force)' : '')
            . ($queueEnabled ? ' via queue' : ' synchronously')
            . '...'
        );

        if ($onlyKeys !== null) {
            $this->warn('--only is currently parsed but not yet applied inside GenerateMediaVariants job.');
        }

        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $query->orderBy('id')->chunkById($chunk, function ($items) use ($force, $queueEnabled, $connection, $queue, $bar) {
            foreach ($items as $media) {
                if ($queueEnabled) {
                    GenerateMediaVariants::dispatch($media->id, $force)
                        ->onConnection($connection)
                        ->onQueue($queue);
                } else {
                    GenerateMediaVariants::dispatchSync($media->id, $force);
                }

                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine();
        $this->info('Done.');

        return self::SUCCESS;
    }
}