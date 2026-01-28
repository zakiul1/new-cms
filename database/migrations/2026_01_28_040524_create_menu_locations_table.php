<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('menu_locations', function (Blueprint $table) {
            $table->string('key')->primary();       // e.g. primary, footer
            $table->string('label');
            $table->string('theme_slug')->nullable(); // optional scoping
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('menu_locations');
    }
};