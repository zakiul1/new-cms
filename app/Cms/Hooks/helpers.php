<?php

use TorMorten\Eventy\Facades\Eventy;

if (!function_exists('add_action')) {
    function add_action(string $hook, callable $callback, int $priority = 20, int $args = 1): void
    {
        Eventy::addAction($hook, $callback, $priority, $args);
    }
}

if (!function_exists('do_action')) {
    function do_action(string $hook, mixed ...$args): mixed
    {
        return Eventy::action($hook, ...$args);
    }
}

if (!function_exists('add_filter')) {
    function add_filter(string $hook, callable $callback, int $priority = 20, int $args = 1): void
    {
        Eventy::addFilter($hook, $callback, $priority, $args);
    }
}

if (!function_exists('apply_filters')) {
    function apply_filters(string $hook, mixed $value, mixed ...$args): mixed
    {
        return Eventy::filter($hook, $value, ...$args);
    }
}