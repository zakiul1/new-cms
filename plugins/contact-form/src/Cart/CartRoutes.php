<?php

namespace Plugins\ContactForm\Cart;

use Illuminate\Support\Facades\Route;

/**
 * CartRoutes
 *
 * Registers OPTIONAL cart-related endpoints (server-side).
 *
 * IMPORTANT:
 * - Do NOT register /_contact/cart.js or /_contact/cart.css here,
 *   because CartAssets already owns those routes via CartAssets::registerRoutes().
 *
 * Keep this file for future endpoints if you want server-side cart APIs.
 */
class CartRoutes
{
    private static bool $registered = false;

    public static function register(): void
    {
        if (self::$registered) {
            return;
        }
        self::$registered = true;

        // Example (optional future routes):
        // Route::post('/_contact/cart/clear', function () {
        //     return response()->json(['ok' => true]);
        // })->name('contact-form.cart.clear');

        // Route::get('/_contact/cart/items', function () {
        //     return response()->json(['items' => []]);
        // })->name('contact-form.cart.items');
    }
}