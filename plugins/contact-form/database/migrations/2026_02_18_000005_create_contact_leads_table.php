<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('contact_leads')) {
            return;
        }

        Schema::create('contact_leads', function (Blueprint $table) {
            $table->id();
            $table->string('name', 200)->nullable();
            $table->string('email', 200)->unique();

            $table->string('country_name', 120)->nullable();
            $table->string('whatsapp', 60)->nullable();

            $table->timestamps();

            $table->index('email');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contact_leads');
    }
};