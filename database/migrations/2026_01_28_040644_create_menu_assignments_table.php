<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('menu_assignments', function (Blueprint $table) {
            $table->string('location_key');
            $table->foreign('location_key')->references('key')->on('menu_locations')->cascadeOnDelete();

            $table->foreignId('menu_id')->constrained('menus')->cascadeOnDelete();

            $table->timestamps();

            $table->unique('location_key');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('menu_assignments');
    }
};