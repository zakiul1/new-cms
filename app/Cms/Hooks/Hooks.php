<?php

namespace App\Cms\Hooks;

use TorMorten\Eventy\Facades\Eventy;

class Hooks
{
    public function addAction(string $hook, callable $callback, int $priority = 20, int $arguments = 1): void
    {
        Eventy::addAction($hook, $callback, $priority, $arguments);
    }

    public function doAction(string $hook, mixed ...$args): void
    {
        Eventy::action($hook, ...$args);
    }

    public function addFilter(string $hook, callable $callback, int $priority = 20, int $arguments = 1): void
    {
        Eventy::addFilter($hook, $callback, $priority, $arguments);
    }

    public function applyFilters(string $hook, mixed $value, mixed ...$args): mixed
    {
        return Eventy::filter($hook, $value, ...$args);
    }
}