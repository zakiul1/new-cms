<?php

namespace App\Cms\Seo;

use App\Cms\Core\SettingsRepository;

class SitemapStorage
{
    public function __construct(private SettingsRepository $settings)
    {
    }

    public function directory(): string
    {
        $dir = (string) $this->settings->get('seo', 'sitemap_directory', '');
        $dir = trim($dir);
        $dir = trim($dir, '/');

        return $dir; // '' means root of public disk
    }

    public function path(string $filename): string
    {
        $dir = $this->directory();

        return $dir === '' ? $filename : ($dir . '/' . $filename);
    }

    public function indexFilename(): string
    {
        return 'sitemap.xml';
    }

    public function indexPath(): string
    {
        return $this->path($this->indexFilename());
    }
}