<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('media_variants', function (Blueprint $table) {
            $table->id();

            $table->foreignId('media_id')->constrained('media')->cascadeOnDelete();

            $table->string('key')->index(); // thumb|medium|large
            $table->string('disk')->default('public');
            $table->string('directory');
            $table->string('filename');

            $table->string('mime_type', 191)->index();
            $table->unsignedBigInteger('size')->default(0);

            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();

            $table->timestamps();

            $table->unique(['media_id', 'key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media_variants');
    }
};
