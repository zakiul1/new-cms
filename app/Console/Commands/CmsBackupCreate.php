<?php

namespace App\Console\Commands;

use App\Cms\Backup\CmsBackupService;
use Illuminate\Console\Command;

class CmsBackupCreate extends Command
{
    protected $signature = 'cms:backup:create {--label=}';
    protected $description = 'Create a CMS content backup (DB + public storage)';

    public function handle(CmsBackupService $svc): int
    {
        $backup = $svc->create($this->option('label'));
        $this->info("Backup created: {$backup->path}");
        return self::SUCCESS;
    }
}