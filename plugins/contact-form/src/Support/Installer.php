<?php

namespace Plugins\ContactForm\Support;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class Installer
{
    public static function ensureInstalled(): void
    {
        try {
            if (!Schema::hasTable('contact_submissions')) {
                Schema::create('contact_submissions', function (Blueprint $table) {
                    $table->id();

                    $table->string('name', 200);
                    $table->string('email', 200);

                    // ✅ WhatsApp + Geo
                    $table->string('whatsapp', 60)->nullable();
                    $table->string('country_name', 120)->nullable();
                    $table->string('country_code', 10)->nullable();

                    // ✅ Website + Reference page
                    $table->string('website_url', 255)->nullable();
                    $table->text('reference_url')->nullable();

                    // ✅ Cart items (JSON string)
                    $table->longText('cart_items')->nullable();

                    $table->string('subject', 256);
                    $table->longText('message');

                    $table->string('ip', 64)->nullable();
                    $table->string('user_agent', 512)->nullable();

                    $table->string('status', 20)->default('pending'); // pending|sent
                    $table->unsignedInteger('attempts')->default(0);
                    $table->text('last_error')->nullable();
                    $table->timestamp('next_retry_at')->nullable();

                    $table->timestamps();

                    $table->index('status');
                    $table->index('next_retry_at');
                });

                return;
            }
        } catch (\Throwable $e) {
            return;
        }

        // Add missing columns safely
        $addColumn = function (string $col, \Closure $cb) {
            try {
                if (!Schema::hasColumn('contact_submissions', $col)) {
                    Schema::table('contact_submissions', function (Blueprint $table) use ($cb) {
                        $cb($table);
                    });
                }
            } catch (\Throwable $e) {
                // ignore
            }
        };

        // existing columns
        $addColumn('ip', fn(Blueprint $t) => $t->string('ip', 64)->nullable());
        $addColumn('user_agent', fn(Blueprint $t) => $t->string('user_agent', 512)->nullable());
        $addColumn('next_retry_at', fn(Blueprint $t) => $t->timestamp('next_retry_at')->nullable());

        // ✅ WhatsApp + Geo columns
        $addColumn('whatsapp', fn(Blueprint $t) => $t->string('whatsapp', 60)->nullable());
        $addColumn('country_name', fn(Blueprint $t) => $t->string('country_name', 120)->nullable());
        $addColumn('country_code', fn(Blueprint $t) => $t->string('country_code', 10)->nullable());

        // ✅ Website + Reference page columns
        $addColumn('website_url', fn(Blueprint $t) => $t->string('website_url', 255)->nullable());
        $addColumn('reference_url', fn(Blueprint $t) => $t->text('reference_url')->nullable());

        // ✅ Cart items column (JSON string)
        $addColumn('cart_items', fn(Blueprint $t) => $t->longText('cart_items')->nullable());
    }
}