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

        // Ensure state shape is correct after hydration
        $this->afterStateHydrated(function (MediaPicker $component, $state): void {
            if ($component->isMultiple()) {
                $component->state(array_values(array_filter(array_map('intval', (array) ($state ?? [])))));
            } else {
                $component->state($state ? (int) $state : null);
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
        $ids = $this->isMultiple()
            ? array_values(array_filter(array_map('intval', (array) ($this->getState() ?? []))))
            : array_values(array_filter([(int) ($this->getState() ?? 0)]));

        if ($ids === []) {
            return [];
        }

        /** @var Collection<int, Media> $media */
        $media = Media::query()
            ->whereIn('id', $ids)
            ->get()
            ->keyBy('id');

        $out = [];
        foreach ($ids as $id) {
            $m = $media->get($id);
            if (!$m) {
                continue;
            }

            $title = (string) ($m->title ?: ($m->original_filename ?: ('Media #' . $m->id)));
            $thumb = (string) ($m->thumbUrl() ?: $m->url());
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
}