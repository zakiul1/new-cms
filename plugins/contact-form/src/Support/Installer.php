<?php

namespace Plugins\ContactForm\Support;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class Installer
{
    public static function ensureInstalled(): void
    {
        // 1) Ensure permanent table exists: contact_leads
        try {
            if (!Schema::hasTable('contact_leads')) {
                Schema::create('contact_leads', function (Blueprint $table) {
                    $table->id();

                    $table->string('name', 200)->nullable();
                    $table->string('email', 200)->unique();

                    $table->string('country_name', 120)->nullable();
                    $table->string('whatsapp', 60)->nullable();

                    $table->timestamps();

                    $table->index('email');
                });
            }
        } catch (\Throwable $e) {
            // ignore
        }

        // 2) Ensure temporary table exists: contact_submissions
        try {
            if (!Schema::hasTable('contact_submissions')) {
                Schema::create('contact_submissions', function (Blueprint $table) {
                    $table->id();

                    // Optional link to permanent lead
                    $table->unsignedBigInteger('lead_id')->nullable()->index();

                    // Keep name/email for admin listing + export
                    $table->string('name', 200);
                    $table->string('email', 200);

                    // WhatsApp + Geo (useful for admin/export; lead keeps permanent too)
                    $table->string('whatsapp', 60)->nullable();
                    $table->string('country_name', 120)->nullable();
                    $table->string('country_code', 10)->nullable();

                    // Website + reference page (optional)
                    $table->string('website_url', 255)->nullable();
                    $table->text('reference_url')->nullable();

                    // Cart items (legacy)
                    $table->longText('cart_items')->nullable();

                    // IMPORTANT: subject/message not stored permanently anymore
                    $table->string('subject', 256)->nullable();
                    $table->longText('message')->nullable();

                    // Encrypted temporary payload (subject/message/cart/etc. for retry/send)
                    $table->longText('payload')->nullable();

                    $table->string('ip', 64)->nullable();
                    $table->string('user_agent', 512)->nullable();

                    $table->string('status', 20)->default('pending'); // pending|sent
                    $table->unsignedInteger('attempts')->default(0);
                    $table->text('last_error')->nullable();
                    $table->timestamp('next_retry_at')->nullable();

                    // When it was successfully sent (used for prune rules)
                    $table->timestamp('sent_at')->nullable()->index();

                    $table->timestamps();

                    $table->index('status');
                    $table->index('next_retry_at');
                });

                return;
            }
        } catch (\Throwable $e) {
            return;
        }

        // 3) Add missing columns safely (self-healing)
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

        // Existing columns
        $addColumn('ip', fn(Blueprint $t) => $t->string('ip', 64)->nullable());
        $addColumn('user_agent', fn(Blueprint $t) => $t->string('user_agent', 512)->nullable());
        $addColumn('next_retry_at', fn(Blueprint $t) => $t->timestamp('next_retry_at')->nullable());

        // WhatsApp + Geo columns
        $addColumn('whatsapp', fn(Blueprint $t) => $t->string('whatsapp', 60)->nullable());
        $addColumn('country_name', fn(Blueprint $t) => $t->string('country_name', 120)->nullable());
        $addColumn('country_code', fn(Blueprint $t) => $t->string('country_code', 10)->nullable());

        // Website + Reference page columns
        $addColumn('website_url', fn(Blueprint $t) => $t->string('website_url', 255)->nullable());
        $addColumn('reference_url', fn(Blueprint $t) => $t->text('reference_url')->nullable());

        // Cart items column (JSON string)
        $addColumn('cart_items', fn(Blueprint $t) => $t->longText('cart_items')->nullable());

        // New columns for your goal
        $addColumn('lead_id', fn(Blueprint $t) => $t->unsignedBigInteger('lead_id')->nullable()->index());
        $addColumn('payload', fn(Blueprint $t) => $t->longText('payload')->nullable());
        $addColumn('sent_at', fn(Blueprint $t) => $t->timestamp('sent_at')->nullable()->index());

        // Make subject/message nullable on older installs (so we can stop storing them)
        try {
            if (Schema::hasColumn('contact_submissions', 'subject')) {
                Schema::table('contact_submissions', function (Blueprint $table) {
                    // Some DBs may not support change() without doctrine/dbal; ignore failures safely.
                    try {
                        $table->string('subject', 256)->nullable()->change();
                    } catch (\Throwable $e) {
                        // ignore
                    }
                });
            }
        } catch (\Throwable $e) {
            // ignore
        }

        try {
            if (Schema::hasColumn('contact_submissions', 'message')) {
                Schema::table('contact_submissions', function (Blueprint $table) {
                    try {
                        $table->longText('message')->nullable()->change();
                    } catch (\Throwable $e) {
                        // ignore
                    }
                });
            }
        } catch (\Throwable $e) {
            // ignore
        }
    }
}