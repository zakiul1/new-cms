<?php

namespace App\Cms\Core;

use Illuminate\Support\Facades\Cache;

class CmsCacheVersions
{
    protected function key(string $group, string $id): string
    {
        return "cms:ver:{$group}:{$id}";
    }

    public function get(string $group, string $id): int
    {
        return (int) Cache::get($this->key($group, $id), 1);
    }

    public function bump(string $group, string $id): int
    {
        $k = $this->key($group, $id);
        $current = (int) Cache::get($k, 1);
        $new = $current + 1;

        Cache::forever($k, $new);

        return $new;
    }

    /**
     * Global render version:
     * bump this when theme/plugins/assets that affect frontend HTML output changes.
     */
    public function renderVersion(): int
    {
        return $this->get('render', 'global');
    }

    public function bumpRender(): int
    {
        return $this->bump('render', 'global');
    }

    /**
     * ✅ Backward compatible alias.
     * Your error shows something is calling getRender().
     */
    public function getRender(): int
    {
        return $this->renderVersion();
    }
}