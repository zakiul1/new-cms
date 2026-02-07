<?php

namespace App\Console\Commands;

use App\Cms\Backup\CmsBackupService;
use App\Models\CmsBackup;
use Illuminate\Console\Command;

class CmsBackupRestore extends Command
{
    protected $signature = 'cms:backup:restore {backup_id} {--no-truncate}';
    protected $description = 'Restore a CMS content backup';

    public function handle(CmsBackupService $svc): int
    {
        $id = (int) $this->argument('backup_id');
        $backup = CmsBackup::query()->findOrFail($id);

        $truncate = !$this->option('no-truncate');
        $svc->restore($backup, $truncate);

        $this->info("Restored backup #{$backup->id}");
        return self::SUCCESS;
    }
}