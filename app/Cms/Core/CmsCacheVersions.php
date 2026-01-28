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

    public function bump(string $group, string $id): void
    {
        $k = $this->key($group, $id);
        $current = (int) Cache::get($k, 1);
        Cache::forever($k, $current + 1);
    }
}