<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('media', function (Blueprint $table) {
            // Root-level attachment permalink slug: /{slug}
            $table->string('slug', 255)->nullable()->unique()->after('title');

            // Per-attachment visibility (WP-like)
            $table->boolean('attachment_public')->default(true)->after('slug');

            // Per-attachment indexable (SEO)
            $table->boolean('attachment_indexable')->default(true)->after('attachment_public');
        });

        // Backfill slugs for existing media so old files also have attachment pages
        DB::transaction(function () {
            $tableName = 'media';

            DB::table($tableName)
                ->whereNull('slug')
                ->orderBy('id')
                ->select(['id', 'title', 'original_filename'])
                ->chunkById(200, function ($rows) use ($tableName) {
                    foreach ($rows as $row) {
                        $name = (string) ($row->title ?: $row->original_filename ?: 'attachment');
                        $name = pathinfo($name, PATHINFO_FILENAME);

                        $base = Str::slug(Str::limit($name, 120, ''));
                        $base = $base !== '' ? $base : 'attachment';

                        do {
                            $slug = $base . '-' . Str::random(10);
                            $exists = DB::table($tableName)->where('slug', $slug)->exists();
                        } while ($exists);

                        DB::table($tableName)
                            ->where('id', $row->id)
                            ->update([
                                'slug' => $slug,
                                'attachment_public' => true,
                                'attachment_indexable' => true,
                            ]);
                    }
                });
        });
    }

    public function down(): void
    {
        Schema::table('media', function (Blueprint $table) {
            $table->dropUnique(['slug']);
            $table->dropColumn(['slug', 'attachment_public', 'attachment_indexable']);
        });
    }
};