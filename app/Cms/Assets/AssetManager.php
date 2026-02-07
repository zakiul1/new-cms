<?php

namespace App\Cms\Assets;

class AssetManager
{
    /**
     * @var array<string, array<int, array{type:string, handle:string, src:string, attrs:array<string,string>}>>
     * groups: frontend, admin, etc.
     */
    private array $assetsByGroup = [];

    public function enqueueStyle(string $handle, string $src, array $attrs = [], string $group = 'frontend'): void
    {
        $this->enqueue('style', $handle, $src, $attrs, $group);
    }

    public function enqueueScript(string $handle, string $src, array $attrs = [], string $group = 'frontend'): void
    {
        $this->enqueue('script', $handle, $src, $attrs, $group);
    }

    public function renderStyles(string $group = 'frontend'): string
    {
        $out = [];
        foreach ($this->assetsByGroup[$group] ?? [] as $a) {
            if ($a['type'] !== 'style') {
                continue;
            }

            $attr = $this->attrsToString($a['attrs']);
            $out[] = '<link rel="stylesheet" href="' . e($a['src']) . '"' . $attr . '>';
        }

        return implode("\n", $out);
    }

    public function renderScripts(string $group = 'frontend'): string
    {
        $out = [];
        foreach ($this->assetsByGroup[$group] ?? [] as $a) {
            if ($a['type'] !== 'script') {
                continue;
            }

            $attr = $this->attrsToString($a['attrs']);
            $out[] = '<script src="' . e($a['src']) . '"' . $attr . '></script>';
        }

        return implode("\n", $out);
    }

    public function clear(?string $group = null): void
    {
        if ($group === null) {
            $this->assetsByGroup = [];
            return;
        }

        unset($this->assetsByGroup[$group]);
    }

    private function enqueue(string $type, string $handle, string $src, array $attrs, string $group): void
    {
        $this->assetsByGroup[$group] ??= [];

        // ✅ dedupe by (type + handle + group)
        foreach ($this->assetsByGroup[$group] as $existing) {
            if ($existing['type'] === $type && $existing['handle'] === $handle) {
                return;
            }
        }

        $this->assetsByGroup[$group][] = [
            'type' => $type,
            'handle' => $handle,
            'src' => $src,
            'attrs' => $attrs,
        ];
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