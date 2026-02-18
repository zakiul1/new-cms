<?php

namespace Plugins\ContactForm;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\ServiceProvider;
use Plugins\ContactForm\Console\PruneContactSubmissionsCommand;

class ContactFormServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__ . '/views', 'plugins.contact-form');

        if ($this->app->runningInConsole()) {
            $this->commands([
                PruneContactSubmissionsCommand::class,
            ]);
        }

        // ✅ auto prune hourly
        $this->callAfterResolving(Schedule::class, function (Schedule $schedule) {
            $schedule->command('contact-form:prune')->hourly();
        });
    }
}