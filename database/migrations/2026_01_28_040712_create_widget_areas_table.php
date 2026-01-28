<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('widget_areas', function (Blueprint $table) {
            $table->string('key')->primary();     // sidebar-1, footer-1
            $table->string('label');
            $table->string('theme_slug')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('widget_areas');
    }
};