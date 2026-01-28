<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('search_documents', function (Blueprint $table) {
            $table->id();

            // What is indexed
            $table->string('entity_type'); // post|page|term|media (start with post/page)
            $table->unsignedBigInteger('entity_id');

            // Searchable fields
            $table->string('title')->nullable();
            $table->longText('content')->nullable();

            // URLs + meta
            $table->string('slug')->nullable();
            $table->string('url')->nullable();
            $table->json('meta')->nullable();

            // Visibility / filtering
            $table->boolean('is_public')->default(true);
            $table->timestamp('published_at')->nullable();

            $table->timestamps();

            $table->unique(['entity_type', 'entity_id']);
            $table->index(['entity_type', 'is_public']);
            $table->index(['published_at']);

            // If you use MySQL/MariaDB, we can do FULLTEXT:
            // (If your DB doesn’t support it, just remove this block)
            // $table->fullText(['title', 'content']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('search_documents');
    }
};