<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('terms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('taxonomy_id')->constrained('taxonomies')->cascadeOnDelete();

            $table->string('name');
            $table->string('slug');
            $table->text('description')->nullable();

            $table->foreignId('parent_id')->nullable()->constrained('terms')->nullOnDelete();

            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['taxonomy_id', 'slug']);
        });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('terms');
    }
};