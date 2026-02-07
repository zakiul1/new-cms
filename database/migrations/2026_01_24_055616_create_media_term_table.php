<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('media_term', function (Blueprint $table) {
            $table->unsignedBigInteger('media_id');
            $table->unsignedBigInteger('term_id');
            $table->timestamps();

            $table->primary(['media_id', 'term_id']);

            $table->foreign('media_id')->references('id')->on('media')->cascadeOnDelete();
            $table->foreign('term_id')->references('id')->on('terms')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media_term');
    }
};