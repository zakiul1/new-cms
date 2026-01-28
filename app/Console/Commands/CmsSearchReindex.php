<?php

namespace App\Console\Commands;

use App\Cms\Search\SearchIndex;
use App\Models\Post;
use App\Models\SearchDocument;
use Illuminate\Console\Command;

class CmsSearchReindex extends Command
{
    protected $signature = 'cms:search-reindex {--truncate : Clear index first}';
    protected $description = 'Rebuild CMS search index for posts/pages';

    public function handle(): int
    {
        if ($this->option('truncate')) {
            SearchDocument::query()->delete();
            $this->info('Search index cleared.');
        }

        $index = app(SearchIndex::class);

        $count = 0;

        Post::query()
            ->orderBy('id')
            ->chunk(200, function ($posts) use (&$count, $index) {
                foreach ($posts as $post) {
                    $index->upsertPost($post);
                    $count++;
                }
            });

        $this->info("Indexed {$count} posts/pages.");
        return self::SUCCESS;
    }
}