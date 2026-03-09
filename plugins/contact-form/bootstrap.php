<?php

use App\Cms\Hooks\HookPoints;
use App\Cms\Hooks\Hooks;
use Filament\Panel;
use Illuminate\Support\Facades\Route;

require_once __DIR__ . '/ContactFormServiceProvider.php';

// models / installer
require_once __DIR__ . '/src/ContactSubmission.php';
require_once __DIR__ . '/src/ContactLead.php';
require_once __DIR__ . '/src/Support/Installer.php';

// console
require_once __DIR__ . '/src/Console/PruneContactSubmissionsCommand.php';

// services
require_once __DIR__ . '/src/Services/EDeskClient.php';
require_once __DIR__ . '/src/Services/SubmissionSender.php';
require_once __DIR__ . '/src/Services/IpCountryResolver.php';

// controllers
require_once __DIR__ . '/src/ContactFormController.php';
require_once __DIR__ . '/src/CronController.php';

// filament pages
require_once __DIR__ . '/src/Filament/Pages/ContactConfig.php';
require_once __DIR__ . '/src/Filament/Pages/ContactSubmissions.php';

// cart
require_once __DIR__ . '/src/Cart/CartRoutes.php';
require_once __DIR__ . '/src/Cart/CartAssets.php';

app()->register(\Plugins\ContactForm\ContactFormServiceProvider::class);

// Admin panel pages
app(Hooks::class)->addAction(HookPoints::FILAMENT_ADMIN_PANEL, function (Panel $panel) {
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

/**
 * Routes (submit + cron + cart endpoints)
 *
 * IMPORTANT:
 * /_contact/cart.js and /_contact/cart.css are static files in public/_contact/
 * so they are NOT registered as Laravel routes.
 */
app()->booted(function () {
    Route::middleware('web')->group(function () {
        if (!Route::has('contact-form.submit')) {
            Route::post('/_contact/submit', [\Plugins\ContactForm\ContactFormController::class, 'submit'])
                ->name('contact-form.submit');
        }

        if (!Route::has('contact-form.cron')) {
            Route::get('/_contact/cron', [\Plugins\ContactForm\CronController::class, 'run'])
                ->name('contact-form.cron');
        }

        if (class_exists(\Plugins\ContactForm\Cart\CartRoutes::class)) {
            \Plugins\ContactForm\Cart\CartRoutes::register();
        }
    });
});

/**
 * IMPORTANT:
 * Do NOT auto-enqueue cart assets here.
 *
 * Reason:
 * CMS_ENQUEUE_ASSETS runs too early in your CMS lifecycle, before final Blade
 * output/shortcodes are reliably known. That caused false negatives and the cart
 * JS stopped loading.
 *
 * Cart CSS/JS must be loaded only from the Blade files that actually render
 * `.cf-get-price` buttons, using:
 *
 * @once
 *     @push('head')
 *         <link rel="stylesheet" href="{{ asset('_contact/cart.css') }}">
 *     @endpush
 *
 *     @push('scripts')
 *         <script src="{{ asset('_contact/cart.js') }}" defer></script>
 *     @endpush
 * @endonce
 */