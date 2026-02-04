<?php

namespace App\Filament\Forms\Components;

use Filament\Forms\Components\Field;

class WpClassicEditor extends Field
{
    protected string $view = 'filament.forms.components.wp-classic-editor';

    protected int $height = 320;

    protected bool $menubar = false;

    protected array $plugins = [
        'link',
        'lists',
        'code',
    ];

    protected array $toolbar = [
        'blocks',
        'bold italic underline',
        'bullist numlist',
        'link blockquote',
        'removeformat',
        'code',
    ];

    public function height(int $height): static
    {
        $this->height = $height;

        return $this;
    }

    public function getHeight(): int
    {
        return $this->height;
    }

    public function menubar(bool $menubar = true): static
    {
        $this->menubar = $menubar;

        return $this;
    }

    public function getMenubar(): bool
    {
        return $this->menubar;
    }

    public function plugins(array $plugins): static
    {
        $this->plugins = $plugins;

        return $this;
    }

    public function getPlugins(): array
    {
        return $this->plugins;
    }

    public function toolbar(array $toolbar): static
    {
        $this->toolbar = $toolbar;

        return $this;
    }

    public function getToolbar(): array
    {
        return $this->toolbar;
    }
}