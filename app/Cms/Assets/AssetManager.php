<?php

namespace App\Cms\Assets;

class AssetManager
{
    /** @var array<int, array{type:string, handle:string, src:string, attrs:array<string,string>}> */
    private array $assets = [];

    public function enqueueStyle(string $handle, string $src, array $attrs = []): void
    {
        $this->assets[] = ['type' => 'style', 'handle' => $handle, 'src' => $src, 'attrs' => $attrs];
    }

    public function enqueueScript(string $handle, string $src, array $attrs = []): void
    {
        $this->assets[] = ['type' => 'script', 'handle' => $handle, 'src' => $src, 'attrs' => $attrs];
    }

    public function renderStyles(): string
    {
        $out = [];
        foreach ($this->assets as $a) {
            if ($a['type'] !== 'style')
                continue;

            $attr = $this->attrsToString($a['attrs']);
            $out[] = '<link rel="stylesheet" href="' . e($a['src']) . '"' . $attr . '>';
        }
        return implode("\n", $out);
    }

    public function renderScripts(): string
    {
        $out = [];
        foreach ($this->assets as $a) {
            if ($a['type'] !== 'script')
                continue;

            $attr = $this->attrsToString($a['attrs']);
            $out[] = '<script src="' . e($a['src']) . '"' . $attr . '></script>';
        }
        return implode("\n", $out);
    }

    private function attrsToString(array $attrs): string
    {
        $parts = [];
        foreach ($attrs as $k => $v) {
            $parts[] = ' ' . e($k) . '="' . e($v) . '"';
        }
        return implode('', $parts);
    }
}