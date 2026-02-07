<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Console\Scheduling\Schedule;

// Keep the default command
Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// ✅ CMS scheduled backups (WP-feel)
// IMPORTANT: to run schedules locally use: php artisan schedule:work
// On server: cron every minute runs: php artisan schedule:run

app(Schedule::class)->command('cms:backup:create --label=Scheduled')->dailyAt('02:00');
app(Schedule::class)->command('cms:backup:prune')->dailyAt('03:00');