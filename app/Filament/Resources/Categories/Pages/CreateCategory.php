<?php

namespace App\Filament\Resources\Categories\Pages;

use App\Filament\Resources\Categories\CategoryResource;
use App\Models\Taxonomy;
use App\Models\Term;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Str;

class CreateCategory extends CreateRecord
{
    protected static string $resource = CategoryResource::class;


    protected function getHeaderActions(): array
    {
        return [
            $this->getCancelFormAction(),
            $this->getCreateAnotherFormAction()->formId('form'),
            $this->getCreateFormAction()->formId('form'),
        ];
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $taxonomyId = Taxonomy::firstOrCreate(
            ['key' => 'category'],
            ['label' => 'Categories', 'hierarchical' => true],
        )->id;

        $data['taxonomy_id'] = $taxonomyId;

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