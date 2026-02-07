<?php

namespace App\Filament\Forms\Components;

use Filament\Forms\Components\Field;

class MonacoEditor extends Field
{
    protected string $view = 'filament.forms.components.monaco-editor';

    protected string|\Closure|null $language = 'php';
    protected string|\Closure|null $height = '70vh';

    public function language(string|\Closure $lang): static
    {
        $this->language = $lang;
        return $this;
    }

    public function height(string|\Closure $height): static
    {
        $this->height = $height;
        return $this;
    }

    public function getLanguage(): string
    {
        $lang = $this->evaluate($this->language);
        return is_string($lang) ? $lang : 'php';
    }

    public function getHeight(): string
    {
        $h = $this->evaluate($this->height);
        return is_string($h) ? $h : '70vh';
    }
}