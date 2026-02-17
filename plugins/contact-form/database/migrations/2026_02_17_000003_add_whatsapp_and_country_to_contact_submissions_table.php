<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('contact_submissions', function (Blueprint $table) {
            $table->string('whatsapp', 60)->nullable()->after('email');
            $table->string('country_name', 120)->nullable()->after('whatsapp');
            $table->string('country_code', 10)->nullable()->after('country_name');
        });
    }

    public function down(): void
    {
        Schema::table('contact_submissions', function (Blueprint $table) {
            $table->dropColumn(['whatsapp', 'country_name', 'country_code']);
        });
    }
};