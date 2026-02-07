<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('post_media', function (Blueprint $table) {
            $table->id();

            $table->foreignId('post_id')->constrained('posts')->cascadeOnDelete();
            $table->foreignId('media_id')->constrained('media')->cascadeOnDelete();

            // gallery | product | attachment (future)
            $table->string('role')->default('product')->index();

            // ordering in UI
            $table->unsignedInteger('sort_order')->default(0);

            $table->timestamps();

            $table->unique(['post_id', 'media_id', 'role']);
            $table->index(['post_id', 'role', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('post_media');
    }
};
