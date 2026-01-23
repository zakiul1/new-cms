<?php

namespace App\Cms\Content\Blocks;

class BlockRenderer
{
    public function __construct(private BlockRegistry $registry)
    {
    }

    public function render(?array $blocks): string
    {
        if (!is_array($blocks))
            return '';

        $html = [];
        foreach ($blocks as $block) {
            if (!is_array($block))
                continue;

            $type = (string) ($block['type'] ?? '');
            $data = (array) ($block['data'] ?? []);

            if ($type === '' || !$this->registry->has($type)) {
                continue;
            }

            $html[] = $this->registry->render($type, $data);
        }

        return implode("\n", $html);
    }
}