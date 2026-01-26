<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('cms_settings', function (Blueprint $table) {
            $table->string('group')->default('general')->change();
        });
    }

    public function down(): void
    {
        Schema::table('cms_settings', function (Blueprint $table) {
            $table->string('group')->default(null)->change(); // adjust if needed
        });
    }
};