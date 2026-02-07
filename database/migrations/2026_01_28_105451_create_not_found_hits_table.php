<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('not_found_hits', function (Blueprint $table) {
            $table->id();

            $table->string('path')->unique();          // "/old-url"
            $table->unsignedBigInteger('hits')->default(0);

            $table->timestamp('first_hit_at')->nullable();
            $table->timestamp('last_hit_at')->nullable();

            $table->string('last_referrer')->nullable();
            $table->string('last_user_agent')->nullable();
            $table->string('last_ip')->nullable();

            $table->timestamps();

            $table->index(['hits', 'last_hit_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('not_found_hits');
    }
};