<?php

use App\Cms\Hooks\HookPoints;
use Filament\Panel;
use Illuminate\Support\Facades\Route;

require_once __DIR__ . '/ContactFormServiceProvider.php';

require_once __DIR__ . '/src/ContactSubmission.php';
require_once __DIR__ . '/src/Support/Installer.php';

// ✅ services
require_once __DIR__ . '/src/Services/EDeskClient.php';
require_once __DIR__ . '/src/Services/SubmissionSender.php';
require_once __DIR__ . '/src/Services/IpCountryResolver.php';

// ✅ controllers
require_once __DIR__ . '/src/ContactFormController.php';
require_once __DIR__ . '/src/CronController.php';

// ✅ filament pages
require_once __DIR__ . '/src/Filament/Pages/ContactConfig.php';
require_once __DIR__ . '/src/Filament/Pages/ContactSubmissions.php';

// ✅ cart
require_once __DIR__ . '/src/Cart/CartRoutes.php';
require_once __DIR__ . '/src/Cart/CartAssets.php';

app()->register(\Plugins\ContactForm\ContactFormServiceProvider::class);

// ✅ Admin panel pages
app(\App\Cms\Hooks\Hooks::class)->addAction(HookPoints::FILAMENT_ADMIN_PANEL, function (Panel $panel) {
    $panel->pages([
        \Plugins\ContactForm\Filament\Pages\ContactConfig::class,
        \Plugins\ContactForm\Filament\Pages\ContactSubmissions::class,
    ]);
});

// ✅ Template option
add_filter('cms.page_template_options', function (array $options) {
    $options['contact'] = 'Contact Page (Contact Form)';
    return $options;
}, 20, 1);

// ✅ Routes (submit + cron + cart endpoints)
add_action(HookPoints::CMS_ROUTES, function () {
    Route::post('/_contact/submit', [\Plugins\ContactForm\ContactFormController::class, 'submit'])
        ->name('contact-form.submit');

    Route::get('/_contact/cron', [\Plugins\ContactForm\CronController::class, 'run'])
        ->name('contact-form.cron');

    // ✅ cart related routes (optional extra endpoints)
    if (class_exists(\Plugins\ContactForm\Cart\CartRoutes::class)) {
        \Plugins\ContactForm\Cart\CartRoutes::register();
    }

    // ✅ cart asset raw routes (/cart.js, /cart.css)
    if (class_exists(\Plugins\ContactForm\Cart\CartAssets::class)) {
        \Plugins\ContactForm\Cart\CartAssets::registerRoutes();
    }
});

/**
 * ✅ Frontend asset enqueue (correct hook)
 *
 * This runs during frontend render, so asset manager / head/footer hooks work.
 * Also prevents loading cart UI on admin panel.
 */
add_action(HookPoints::CMS_ENQUEUE_ASSETS, function () {
    // Prevent admin/filament side
    $path = (string) request()->path();
    if (str_starts_with($path, 'lara-admin') || str_contains($path, 'filament')) {
        return;
    }

    if (class_exists(\Plugins\ContactForm\Cart\CartAssets::class)) {
        \Plugins\ContactForm\Cart\CartAssets::enqueue();
    }
});