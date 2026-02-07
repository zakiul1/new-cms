<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('menu_items', function (Blueprint $table) {
            if (!Schema::hasColumn('menu_items', 'object_type')) {
                $table->string('object_type')->nullable()->after('type'); // post|term|custom|dynamic
            }
            if (!Schema::hasColumn('menu_items', 'object_id')) {
                $table->unsignedBigInteger('object_id')->nullable()->after('object_type');
            }
            if (!Schema::hasColumn('menu_items', 'taxonomy_key')) {
                $table->string('taxonomy_key')->nullable()->after('object_id');
            }

            if (!Schema::hasColumn('menu_items', 'target')) {
                $table->string('target')->nullable()->after('url'); // _blank
            }
            if (!Schema::hasColumn('menu_items', 'rel')) {
                $table->string('rel')->nullable()->after('target'); // "nofollow ugc sponsored"
            }
            if (!Schema::hasColumn('menu_items', 'css_class')) {
                $table->string('css_class')->nullable()->after('rel');
            }
            if (!Schema::hasColumn('menu_items', 'css_id')) {
                $table->string('css_id')->nullable()->after('css_class');
            }
            if (!Schema::hasColumn('menu_items', 'icon')) {
                $table->string('icon')->nullable()->after('css_id');
            }
            if (!Schema::hasColumn('menu_items', 'description')) {
                $table->text('description')->nullable()->after('icon');
            }

            if (!Schema::hasColumn('menu_items', 'visibility')) {
                $table->json('visibility')->nullable()->after('description');
            }
            // You already have `data` in your form; keep it as JSON for mega config etc.
            if (!Schema::hasColumn('menu_items', 'data')) {
                $table->json('data')->nullable()->after('visibility');
            }
        });
    }

    public function down(): void
    {
        Schema::table('menu_items', function (Blueprint $table) {
            foreach ([
                'object_type',
                'object_id',
                'taxonomy_key',
                'target',
                'rel',
                'css_class',
                'css_id',
                'icon',
                'description',
                'visibility',
                'data',
            ] as $col) {
                if (Schema::hasColumn('menu_items', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};