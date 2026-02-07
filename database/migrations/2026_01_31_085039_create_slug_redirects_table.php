<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('slug_redirects', function (Blueprint $table) {
            $table->id();
            $table->string('from_path')->unique(); // old url path: /old-category/old-slug
            $table->string('to_path');             // new url path: /new-category/new-slug
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('slug_redirects');
    }
};