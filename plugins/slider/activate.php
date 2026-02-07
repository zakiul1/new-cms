<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

if (!Schema::hasTable('sliders')) {
    Schema::create('sliders', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->string('key')->unique();     // ex: home-hero, homepage-top, product-hero
        $table->boolean('is_active')->default(true);
        $table->json('settings_json')->nullable(); // ✅ per-slider settings
        $table->timestamps();
    });
} else {
    // ✅ add column if you already created table before
    if (!Schema::hasColumn('sliders', 'settings_json')) {
        Schema::table('sliders', function (Blueprint $table) {
            $table->json('settings_json')->nullable()->after('is_active');
        });
    }
}

if (!Schema::hasTable('slides')) {
    Schema::create('slides', function (Blueprint $table) {
        $table->id();
        $table->foreignId('slider_id')->constrained('sliders')->cascadeOnDelete();

        $table->unsignedBigInteger('media_id')->nullable();
        $table->string('title')->nullable();
        $table->string('subtitle')->nullable();

        $table->string('button_text')->nullable();
        $table->string('button_url')->nullable();
        $table->boolean('button_new_tab')->default(false);

        $table->unsignedInteger('sort_order')->default(1);
        $table->boolean('is_active')->default(true);

        $table->timestamps();

        $table->index(['slider_id', 'sort_order']);
        $table->index(['slider_id', 'is_active']);
    });
}
