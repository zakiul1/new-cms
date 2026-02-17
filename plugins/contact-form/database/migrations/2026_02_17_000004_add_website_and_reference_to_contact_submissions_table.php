<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('contact_submissions', function (Blueprint $table) {
            $table->string('website_url', 255)->nullable()->after('country_code');
            $table->text('reference_url')->nullable()->after('website_url');
        });
    }

    public function down(): void
    {
        Schema::table('contact_submissions', function (Blueprint $table) {
            $table->dropColumn(['website_url', 'reference_url']);
        });
    }
};