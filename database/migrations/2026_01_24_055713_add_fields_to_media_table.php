<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('media', function (Blueprint $table) {
            if (!Schema::hasColumn('media', 'disk')) {
                $table->string('disk')->default('public')->after('id');
            }

            if (!Schema::hasColumn('media', 'path')) {
                $table->string('path')->nullable()->after('disk'); // original stored path
            }

            if (!Schema::hasColumn('media', 'hash')) {
                $table->string('hash', 64)->nullable()->index()->after('path');
            }

            if (!Schema::hasColumn('media', 'width')) {
                $table->unsignedInteger('width')->nullable();
            }

            if (!Schema::hasColumn('media', 'height')) {
                $table->unsignedInteger('height')->nullable();
            }

            if (!Schema::hasColumn('media', 'variants')) {
                $table->json('variants')->nullable(); // thumb/medium/large/webp/avif paths
            }

            if (!Schema::hasColumn('media', 'processed_at')) {
                $table->timestamp('processed_at')->nullable();
            }
        });

        // optional uniqueness for duplicates (enable only if you want strict)
        // Schema::table('media', fn (Blueprint $t) => $t->unique('hash'));
    }

    public function down(): void
    {
        Schema::table('media', function (Blueprint $table) {
            foreach (['disk', 'path', 'hash', 'width', 'height', 'variants', 'processed_at'] as $col) {
                if (Schema::hasColumn('media', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};