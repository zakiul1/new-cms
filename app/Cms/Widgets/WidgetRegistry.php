<?php

namespace App\Cms\Widgets;

use App\Cms\Widgets\Contracts\WidgetType;

class WidgetRegistry
{
    /** @var array<string, class-string<WidgetType>> */
    protected array $types = [];

    /** @param class-string<WidgetType> $class */
    public function register(string $class): void
    {
        $key = $class::key();
        $this->types[$key] = $class;
    }

    /** @return array<string, class-string<WidgetType>> */
    public function all(): array
    {
        return $this->types;
    }

    /** @return class-string<WidgetType>|null */
    public function get(string $key): ?string
    {
        return $this->types[$key] ?? null;
    }

    /** @return array<string,string> key => label */
    public function options(): array
    {
        $out = [];
        foreach ($this->types as $key => $class) {
            $out[$key] = $class::name();
        }
        asort($out);
        return $out;
    }
}