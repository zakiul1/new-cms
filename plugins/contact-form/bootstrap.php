<?php

use App\Cms\Hooks\HookPoints;
use Filament\Panel;
use Illuminate\Support\Facades\Route;

require_once __DIR__ . '/ContactFormServiceProvider.php';

require_once __DIR__ . '/src/ContactSubmission.php';
require_once __DIR__ . '/src/Support/Installer.php';
require_once __DIR__ . '/src/Services/EDeskClient.php';
require_once __DIR__ . '/src/Services/SubmissionSender.php';
require_once __DIR__ . '/src/ContactFormController.php';
require_once __DIR__ . '/src/CronController.php';

require_once __DIR__ . '/src/Filament/Pages/ContactConfig.php';
require_once __DIR__ . '/src/Filament/Pages/ContactSubmissions.php';

app()->register(\Plugins\ContactForm\ContactFormServiceProvider::class);

app(\App\Cms\Hooks\Hooks::class)->addAction(HookPoints::FILAMENT_ADMIN_PANEL, function (Panel $panel) {
    $panel->pages([
        \Plugins\ContactForm\Filament\Pages\ContactConfig::class,
        \Plugins\ContactForm\Filament\Pages\ContactSubmissions::class,
    ]);
});

// Template option
add_filter('cms.page_template_options', function (array $options) {
    $options['contact'] = 'Contact Page (Contact Form)';
    return $options;
}, 20, 1);

// Routes (submit + cron)
add_action(HookPoints::CMS_ROUTES, function () {
    Route::post('/_contact/submit', [\Plugins\ContactForm\ContactFormController::class, 'submit'])
        ->name('contact-form.submit');

    Route::get('/_contact/cron', [\Plugins\ContactForm\CronController::class, 'run'])
        ->name('contact-form.cron');
});