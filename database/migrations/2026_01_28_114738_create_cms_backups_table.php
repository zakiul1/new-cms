<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('cms_backups', function (Blueprint $table) {
            $table->id();
            $table->string('disk')->default('local');
            $table->string('path'); // disk path to zip
            $table->string('label')->nullable(); // optional name in UI
            $table->unsignedBigInteger('size_bytes')->default(0);
            $table->string('sha1', 40)->nullable();
            $table->json('meta')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['disk', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cms_backups');
    }
};