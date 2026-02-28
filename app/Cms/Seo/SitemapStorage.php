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

    /**
     * Validate a sitemap name (without ".xml").
     * Allows:
     * - post
     * - page
     * - siatex-tags
     * - siatex-tags-2
     */
    public function isValidSitemapName(string $name): bool
    {
        if ($name === '') {
            return false;
        }

        // Prevent traversal characters
        if (str_contains($name, '..') || str_contains($name, '/') || str_contains($name, '\\')) {
            return false;
        }

        return (bool) preg_match('/^[A-Za-z0-9\-_]+(?:-\d+)?$/', $name);
    }

    /**
     * Convert a sitemap name (no extension) to filename (with .xml).
     */
    public function filenameFromName(string $name): string
    {
        return $name . '.xml';
    }

    /**
     * Validate a sitemap part filename like:
     * - post.xml
     * - post-2.xml
     * - siatex-tags.xml
     * - siatex-tags-3.xml
     */
    public function isSitemapPartFilename(string $filename): bool
    {
        if ($filename === '' || $filename === $this->indexFilename()) {
            return false;
        }

        // Prevent traversal
        if (str_contains($filename, '..') || str_contains($filename, '/') || str_contains($filename, '\\')) {
            return false;
        }

        return (bool) preg_match('/^[A-Za-z0-9\-_]+(?:-\d+)?\.xml$/', $filename);
    }
}