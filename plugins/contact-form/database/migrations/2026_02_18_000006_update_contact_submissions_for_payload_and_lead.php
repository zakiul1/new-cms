<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('contact_submissions')) {
            return;
        }

        Schema::table('contact_submissions', function (Blueprint $table) {
            if (!Schema::hasColumn('contact_submissions', 'lead_id')) {
                $table->unsignedBigInteger('lead_id')->nullable()->index();
            }
            if (!Schema::hasColumn('contact_submissions', 'payload')) {
                $table->longText('payload')->nullable(); // encrypted json
            }
            if (!Schema::hasColumn('contact_submissions', 'sent_at')) {
                $table->timestamp('sent_at')->nullable()->index();
            }
        });
    }

    public function down(): void
    {
        // optional: you can leave empty to avoid breaking old installs
    }
};