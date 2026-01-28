<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('menu_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('menu_id')->constrained('menus')->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('menu_items')->nullOnDelete();

            $table->string('label')->nullable();
            $table->string('type'); // custom_url, post, page, term, route, heading, separator, etc.
            $table->string('url')->nullable();

            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_enabled')->default(true);

            // premium-ready: icon/badge/target/nofollow/mega/visibility/etc
            $table->json('data')->nullable();

            $table->timestamps();

            $table->index(['menu_id', 'parent_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('menu_items');
    }
};