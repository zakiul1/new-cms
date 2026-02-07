<?php

namespace App\Cms\Content\Blocks;

class BlockRegistry
{
    /** @var array<string, callable(array):string> */
    private array $renderers = [];

    public function register(string $type, callable $renderer): void
    {
        $this->renderers[$type] = $renderer;
    }

    public function has(string $type): bool
    {
        return array_key_exists($type, $this->renderers);
    }

    public function render(string $type, array $data): string
    {
        $renderer = $this->renderers[$type] ?? null;
        if (!$renderer)
            return '';

        return (string) $renderer($data);
    }
}