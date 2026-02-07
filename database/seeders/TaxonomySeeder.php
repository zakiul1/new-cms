<?php

namespace Database\Seeders;

use App\Models\Taxonomy;
use Illuminate\Database\Seeder;

class TaxonomySeeder extends Seeder
{
    public function run(): void
    {
        Taxonomy::firstOrCreate(
            ['key' => 'category'],
            ['label' => 'Categories', 'hierarchical' => true],
        );

        Taxonomy::firstOrCreate(
            ['key' => 'tag'],
            ['label' => 'Tags', 'hierarchical' => false],
        );

        // ✅ NEW: Media Categories
        Taxonomy::firstOrCreate(
            ['key' => 'media_category'],
            ['label' => 'Media Categories', 'hierarchical' => true],
        );
    }
}