<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // 1) Add format + ensure FK-supporting index exists BEFORE dropping the unique
        Schema::table('media_variants', function (Blueprint $table) {
            if (!Schema::hasColumn('media_variants', 'format')) {
                $table->string('format', 10)->default('webp')->after('key'); // webp|jpeg|png|avif
            }

            // Important: ensure there is a non-unique index for FK media_id
            // so we can safely drop the old UNIQUE(media_id, key)
            $table->index('media_id', 'media_variants_media_id_idx');
        });

        // 2) Now safely swap the unique index
        Schema::table('media_variants', function (Blueprint $table) {
            // Drop old unique (Laravel-generated name from unique(['media_id','key']))
            $table->dropUnique('media_variants_media_id_key_unique');

            // New unique allows multiple formats per key
            $table->unique(['media_id', 'key', 'format'], 'media_variants_media_id_key_format_unique');

            // Optional helpful index
            $table->index(['media_id', 'format'], 'media_variants_media_id_format_idx');
        });
    }

    public function down(): void
    {
        // Reverse in safe order
        Schema::table('media_variants', function (Blueprint $table) {
            $table->dropIndex('media_variants_media_id_format_idx');
            $table->dropUnique('media_variants_media_id_key_format_unique');

            // Restore old unique
            $table->unique(['media_id', 'key'], 'media_variants_media_id_key_unique');
        });

        Schema::table('media_variants', function (Blueprint $table) {
            // Keep media_id index (safe to keep), but if you want to remove:
            $table->dropIndex('media_variants_media_id_idx');

            if (Schema::hasColumn('media_variants', 'format')) {
                $table->dropColumn('format');
            }
        });
    }
};