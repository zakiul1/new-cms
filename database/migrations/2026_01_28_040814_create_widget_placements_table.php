<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('widget_placements', function (Blueprint $table) {
            $table->id();

            $table->string('widget_area_key');
            $table->foreign('widget_area_key')->references('key')->on('widget_areas')->cascadeOnDelete();

            $table->foreignId('widget_id')->constrained('widgets')->cascadeOnDelete();

            $table->unsignedInteger('sort_order')->default(0);

            // premium-ready
            $table->json('overrides')->nullable();
            $table->json('visibility')->nullable();

            $table->timestamps();

            $table->index(['widget_area_key', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('widget_placements');
    }
};