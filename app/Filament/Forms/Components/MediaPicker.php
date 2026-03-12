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

    protected string|Closure $type = 'image';

    protected function setUp(): void
    {
        parent::setUp();

        $this->afterStateHydrated(function (MediaPicker $component, $state): void {
            if ($component->isMultiple()) {
                $ids = array_values(array_filter(array_map('intval', (array) ($state ?? []))));

                if ($ids !== []) {
                    $existingQuery = Media::query()->whereIn('id', $ids);

                    if ($component->getType() === 'image') {
                        $existingQuery->where('mime_type', 'like', 'image/%');
                    }

                    $existing = $existingQuery
                        ->pluck('id')
                        ->map(fn($id) => (int) $id)
                        ->all();

                    $set = array_flip($existing);

                    $ids = array_values(array_filter($ids, fn($id) => isset($set[$id])));
                }

                if (($maxItems = $component->getMaxItems()) !== null) {
                    $ids = array_slice($ids, 0, $maxItems);
                }

                $component->state($ids);

                return;
            }

            $id = $state ? (int) $state : null;

            if ($id) {
                $query = Media::query()->whereKey($id);

                if ($component->getType() === 'image') {
                    $query->where('mime_type', 'like', 'image/%');
                }

                if (!$query->exists()) {
                    $id = null;
                }
            }

            $component->state($id);
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

    public function type(string|Closure $type = 'image'): static
    {
        $this->type = $type;

        return $this;
    }

    public function image(): static
    {
        $this->type = 'image';

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

    public function getType(): string
    {
        $type = (string) $this->evaluate($this->type);

        return in_array($type, ['all', 'image'], true) ? $type : 'image';
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

        $query = Media::query()
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
                'variantRecords',
                'terms',
            ]);

        if ($this->getType() === 'image') {
            $query->where('mime_type', 'like', 'image/%');
        }

        /** @var Collection<int, Media> $media */
        $media = $query->get()->keyBy('id');

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
            $ids = array_values(array_filter(array_map('intval', (array) ($state ?? []))));

            if (($maxItems = $this->getMaxItems()) !== null) {
                $ids = array_slice($ids, 0, $maxItems);
            }

            return $ids;
        }

        $id = (int) ($state ?? 0);

        return $id > 0 ? [$id] : [];
    }
}