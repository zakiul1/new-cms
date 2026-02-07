<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('media', function (Blueprint $table) {
            $table->id();

            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();

            $table->string('disk')->default('public');
            $table->string('directory');              // e.g. media/2026/01
            $table->string('filename');               // stored filename
            $table->string('original_filename');      // user filename

            $table->string('mime_type', 191)->index();
            $table->unsignedBigInteger('size')->default(0);

            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();

            $table->string('sha1', 40)->nullable()->index();

            $table->string('title')->nullable();
            $table->string('alt')->nullable();
            $table->text('caption')->nullable();
            $table->longText('description')->nullable();

            $table->json('meta')->nullable();

            $table->timestamps();

            $table->index(['disk', 'directory']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media');
    }
};
