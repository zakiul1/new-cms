<?php

namespace App\Filament\Resources\Tags\Pages;

use App\Filament\Resources\Tags\TagResource;
use App\Models\Taxonomy;
use App\Models\Term;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Str;

class CreateTag extends CreateRecord
{
    protected static string $resource = TagResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $taxonomyId = Taxonomy::firstOrCreate(
            ['key' => 'tag'],
            ['label' => 'Tags', 'hierarchical' => false],
        )->id;

        $data['taxonomy_id'] = $taxonomyId;
        $data['parent_id'] = null;

        $base = filled($data['slug'] ?? null)
            ? Str::slug((string) $data['slug'])
            : Str::slug((string) ($data['name'] ?? ''));

        $data['slug'] = $this->uniqueTermSlug($taxonomyId, $base);

        return $data;
    }

    private function uniqueTermSlug(int $taxonomyId, string $baseSlug, ?int $ignoreId = null): string
    {
        $slug = $baseSlug !== '' ? $baseSlug : 'term';
        $i = 2;

        while (
            Term::query()
                ->where('taxonomy_id', $taxonomyId)
                ->where('slug', $slug)
                ->when($ignoreId, fn($q) => $q->whereKeyNot($ignoreId))
                ->exists()
        ) {
            $slug = $baseSlug . '-' . $i;
            $i++;
        }

        return $slug;
    }
}