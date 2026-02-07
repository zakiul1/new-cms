<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('slug_histories', function (Blueprint $table) {
            $table->id();
            $table->string('entity_type'); // 'post'
            $table->unsignedBigInteger('entity_id');
            $table->string('old_slug');
            $table->timestamps();

            $table->index(['entity_type', 'entity_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('slug_histories');
    }
};