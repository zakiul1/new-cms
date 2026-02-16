<?php

namespace App\Filament\Forms\Components;

use App\Models\Media;
use Closure;
use Filament\Forms\Components\Field;
use Illuminate\Support\Collection;

class MediaPicker extends Field
{
    protected string $view = 'filament.forms.components.media-picker';

    protected bool|Closure $multiple = false;

    protected int|Closure|null $maxItems = null;

    protected string|Closure|null $modalHeading = null;

    protected function setUp(): void
    {
        parent::setUp();

        // ✅ Ensure state shape is correct after hydration AND filter invalid/deleted media IDs
        $this->afterStateHydrated(function (MediaPicker $component, $state): void {
            if ($component->isMultiple()) {
                $ids = array_values(array_filter(array_map('intval', (array) ($state ?? []))));

                // ✅ keep only IDs that exist in `media` table (prevents FK errors on save)
                if ($ids !== []) {
                    $existing = Media::query()
                        ->whereIn('id', $ids)
                        ->pluck('id')
                        ->map(fn($id) => (int) $id)
                        ->all();

                    $set = array_flip($existing);

                    // keep original order, drop missing
                    $ids = array_values(array_filter($ids, fn($id) => isset($set[$id])));
                }

                $component->state($ids);
            } else {
                $id = $state ? (int) $state : null;

                // ✅ single: if selected media doesn't exist anymore, clear it
                if ($id && !Media::query()->whereKey($id)->exists()) {
                    $id = null;
                }

                $component->state($id);
            }
        });
    }

    public function multiple(bool|Closure $multiple = true): static
    {
        $this->multiple = $multiple;

        return $this;
    }

    public function maxItems(int|Closure|null $maxItems): static
    {
        $this->maxItems = $maxItems;

        return $this;
    }

    public function modalHeading(string|Closure|null $heading): static
    {
        $this->modalHeading = $heading;

        return $this;
    }

    public function isMultiple(): bool
    {
        return (bool) $this->evaluate($this->multiple);
    }

    public function getMaxItems(): ?int
    {
        $value = $this->evaluate($this->maxItems);

        return is_null($value) ? null : (int) $value;
    }

    public function getModalHeading(): string
    {
        return (string) ($this->evaluate($this->modalHeading) ?: ($this->isMultiple() ? 'Select images' : 'Select image'));
    }

    /**
     * @return array<int, array{id:int, title:string, thumb:string, url:string}>
     */
    public function getSelectedMedia(): array
    {
        $ids = $this->normalizeIds($this->getState());

        if ($ids === []) {
            return [];
        }

        /** @var Collection<int, Media> $media */
        $media = Media::query()
            ->select([
                'id',
                'title',
                'original_filename',
                'mime_type',
                'size',
                'directory',
                'filename',
                'disk',
            ])
            ->whereIn('id', $ids)
            ->with([
                'variantRecords', // helps thumbUrl fast
                'terms',          // for category/folder filter/badges in picker view
            ])
            ->get()
            ->keyBy('id');

        // Preserve selected order
        $out = [];
        foreach ($ids as $id) {
            $m = $media->get($id);
            if (!$m) {
                continue;
            }

            $title = (string) ($m->title ?: ($m->original_filename ?: ('Media #' . $m->id)));

            $thumb = (string) (
                $m->thumbUrl('jpeg')
                ?: $m->thumbUrl()
                ?: $m->url()
            );

            $url = (string) $m->url();

            $out[] = [
                'id' => (int) $m->id,
                'title' => $title,
                'thumb' => $thumb,
                'url' => $url,
            ];
        }

        return $out;
    }

    /**
     * Normalize the stored state into ordered IDs.
     *
     * @return array<int>
     */
    protected function normalizeIds(mixed $state): array
    {
        if ($this->isMultiple()) {
            return array_values(array_filter(array_map('intval', (array) ($state ?? []))));
        }

        $id = (int) ($state ?? 0);

        return $id > 0 ? [$id] : [];
    }
}