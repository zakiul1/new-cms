<?php

namespace Plugins\ContactForm\Support;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class Installer
{
    public static function ensureInstalled(): void
    {
        // Silent by design (shared hosting safe)
        try {
            if (!Schema::hasTable('contact_submissions')) {
                Schema::create('contact_submissions', function (Blueprint $table) {
                    $table->id();

                    $table->string('name', 200);
                    $table->string('email', 200);
                    $table->string('subject', 256);
                    $table->longText('message');

                    $table->string('ip', 64)->nullable();
                    $table->string('user_agent', 512)->nullable();

                    $table->string('status', 20)->default('pending'); // pending|sent
                    $table->unsignedInteger('attempts')->default(0);
                    $table->text('last_error')->nullable();
                    $table->timestamp('next_retry_at')->nullable();

                    $table->timestamps();

                    // Indexes at create-time (most reliable)
                    $table->index('status');
                    $table->index('next_retry_at');
                });

                return;
            }
        } catch (\Throwable $e) {
            return; // can't create table -> stop silently
        }

        // Existing table: try to add missing columns safely.
        // Do each column in its own try/catch so one failure doesn't block others.
        try {
            if (!Schema::hasColumn('contact_submissions', 'ip')) {
                Schema::table('contact_submissions', function (Blueprint $table) {
                    $table->string('ip', 64)->nullable();
                });
            }
        } catch (\Throwable $e) {
            // ignore
        }

        try {
            if (!Schema::hasColumn('contact_submissions', 'user_agent')) {
                Schema::table('contact_submissions', function (Blueprint $table) {
                    $table->string('user_agent', 512)->nullable();
                });
            }
        } catch (\Throwable $e) {
            // ignore
        }

        try {
            if (!Schema::hasColumn('contact_submissions', 'next_retry_at')) {
                Schema::table('contact_submissions', function (Blueprint $table) {
                    $table->timestamp('next_retry_at')->nullable();
                });
            }
        } catch (\Throwable $e) {
            // ignore
        }

        // Optional indexes (can fail on shared hosting, so keep silent)
        try {
            // If index already exists, MySQL will error; so we don't force it.
            // It's okay to skip indexes on shared hosting.
        } catch (\Throwable $e) {
            // ignore
        }
    }
}