<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('posts', function (Blueprint $table) {
            $table->id();

            $table->string('type')->index(); // post | page (and future custom)
            $table->string('title');
            $table->string('slug');

            $table->text('excerpt')->nullable();

            $table->json('content_json')->nullable(); // blocks JSON
            $table->string('status')->index(); // draft | published | scheduled
            $table->timestamp('published_at')->nullable()->index();

            $table->foreignId('author_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedBigInteger('featured_media_id')->nullable(); // Phase 3 will FK to media

            $table->json('meta_json')->nullable();

            $table->timestamps();

            // Unique per type (page slug can match post slug if you want; here we keep unique per type)
            $table->unique(['type', 'slug']);
            $table->index(['status', 'published_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('posts');
    }
};