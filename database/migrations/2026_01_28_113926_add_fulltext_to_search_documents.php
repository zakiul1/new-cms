<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('search_documents', function (Blueprint $table) {
            // Name it explicitly so drop is easy + reliable
            $table->fullText(['title', 'content'], 'sd_title_content_fulltext');
        });
    }

    public function down(): void
    {
        Schema::table('search_documents', function (Blueprint $table) {
            $table->dropFullText('sd_title_content_fulltext');
        });
    }
};