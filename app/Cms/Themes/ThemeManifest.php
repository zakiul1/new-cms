<?php

namespace App\Cms\Themes;

final class ThemeManifest
{
    public function __construct(
        public string $name,
        public string $slug,
        public string $version,
        public ?string $author = null,
        public ?string $description = null,
        public ?string $parent = null,
        /** @var array<string,string> */
        public array $templates = [],
        /** @var array<string,string> */
        public array $menus = [],
        /** @var array<string,string> */
        public array $sidebars = [],
        /** @var array{styles?:array<int,string>,scripts?:array<int,array|string>} */
        public array $assets = [],
        public array $raw = [],
    ) {
    }
}