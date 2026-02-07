<?php

namespace App\Console\Commands;

use App\Cms\Backup\CmsBackupService;
use Illuminate\Console\Command;

class CmsBackupPrune extends Command
{
    protected $signature = 'cms:backup:prune';
    protected $description = 'Prune old CMS backups based on retention settings';

    public function handle(CmsBackupService $svc): int
    {
        $n = $svc->prune();
        $this->info("Pruned {$n} backups.");
        return self::SUCCESS;
    }
}