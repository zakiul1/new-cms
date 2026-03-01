<?php

use App\Cms\Core\Settings;
use App\Cms\Hooks\HookPoints;
use Filament\Panel;
use Illuminate\Support\Facades\Route;

require_once __DIR__ . '/ContactFormServiceProvider.php';

// ✅ models / installer
require_once __DIR__ . '/src/ContactSubmission.php';
require_once __DIR__ . '/src/ContactLead.php';
require_once __DIR__ . '/src/Support/Installer.php';

// ✅ console
require_once __DIR__ . '/src/Console/PruneContactSubmissionsCommand.php';

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

/**
 * ✅ Routes (submit + cron + cart endpoints)
 *
 * IMPORTANT:
 * We still register submit/cron/cart endpoints via Laravel boot cycle.
 * BUT we do NOT register /_contact/cart.js and /_contact/cart.css as routes anymore,
 * because those are now static files in public/_contact/.
 */
app()->booted(function () {
    Route::middleware('web')->group(function () {

        // Avoid duplicates if something registers twice
        if (!Route::has('contact-form.submit')) {
            Route::post('/_contact/submit', [\Plugins\ContactForm\ContactFormController::class, 'submit'])
                ->name('contact-form.submit');
        }

        if (!Route::has('contact-form.cron')) {
            Route::get('/_contact/cron', [\Plugins\ContactForm\CronController::class, 'run'])
                ->name('contact-form.cron');
        }

        // ✅ cart related routes (optional extra endpoints)
        if (class_exists(\Plugins\ContactForm\Cart\CartRoutes::class)) {
            \Plugins\ContactForm\Cart\CartRoutes::register();
        }

        // ✅ IMPORTANT: removed CartAssets::registerRoutes()
        // Because cart.js/cart.css are now static:
        //   public/_contact/cart.js
        //   public/_contact/cart.css
    });
});

/**
 * ✅ Frontend asset enqueue
 * - prevents loading on admin panel
 * - respects cart_enabled setting
 */
add_action(HookPoints::CMS_ENQUEUE_ASSETS, function () {
    // Prevent admin/filament side
    $path = (string) request()->path();
    if (str_starts_with($path, 'lara-admin') || str_contains($path, 'filament')) {
        return;
    }

    // ✅ Respect setting (optional but recommended)
    try {
        $settings = app(Settings::class);
        $cartEnabled = (bool) $settings->get('cart_enabled', true, 'plugin:contact-form');
        if (!$cartEnabled) {
            return;
        }
    } catch (\Throwable $e) {
        // If settings not available, continue safely
    }

    if (class_exists(\Plugins\ContactForm\Cart\CartAssets::class)) {
        \Plugins\ContactForm\Cart\CartAssets::enqueue();
    }
});