<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('terms', function (Blueprint $table) {
            $table->string('product')->nullable()->after('name');
        });

        // Backfill existing rows: product = name (only if product empty)
        DB::table('terms')
            ->whereNull('product')
            ->orWhere('product', '')
            ->update(['product' => DB::raw('name')]);
    }

    public function down(): void
    {
        Schema::table('terms', function (Blueprint $table) {
            $table->dropColumn('product');
        });
    }
};