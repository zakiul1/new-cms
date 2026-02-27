<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

if (!Schema::hasTable('siatex_tags')) {
    Schema::create('siatex_tags', function (Blueprint $table) {
        $table->id();

        $table->string('slug')->unique();
        $table->string('title'); // H1
        $table->json('content_json')->nullable(); // editor html inside ['html'=>...]
        $table->json('meta_json')->nullable(); // subtitle, sub_description, seo, assets

        // selected media category (terms.id where taxonomy_id = media_category)
        $table->unsignedBigInteger('media_category_term_id')->nullable()->index();

        $table->timestamps();
    });
}