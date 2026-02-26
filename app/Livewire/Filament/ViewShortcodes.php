<?php

namespace App\Livewire\Filament;

use App\Cms\Content\Shortcodes\ShortcodeRegistry;
use Livewire\Component;

class ViewShortcodes extends Component
{
    public bool $open = false;
    public string $search = '';

    public function openModal(): void
    {
        $this->open = true;
    }

    public function closeModal(): void
    {
        $this->open = false;
    }

    public function getShortcodesProperty(): array
    {
        $defs = app(ShortcodeRegistry::class)->definitions();

        if (trim($this->search) === '') {
            return $defs;
        }

        $q = mb_strtolower(trim($this->search));

        return array_values(array_filter($defs, function ($d) use ($q) {
            $hay = mb_strtolower(
                ($d['tag'] ?? '') . ' ' .
                ($d['description'] ?? '') . ' ' .
                json_encode($d['params'] ?? [])
            );

            return str_contains($hay, $q);
        }));
    }

    public function render()
    {
        return view('livewire.filament.view-shortcodes');
    }
}