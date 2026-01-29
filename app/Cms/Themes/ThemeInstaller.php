<?php

namespace App\Cms\Themes;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use RuntimeException;
use ZipArchive;

final class ThemeInstaller
{
    public function __construct(
        private ThemeManifestReader $reader,
        private ThemePublisher $publisher,
    ) {
    }

    /**
     * Install theme zip and return a UI-safe manifest array.
     *
     * @return array{
     *   name:string,slug:string,version:string,
     *   author:?string,description:?string,parent:?string,
     *   templates:array,menus:array,sidebars:array,assets:array,raw:array
     * }
     */
    public function installFromZip(UploadedFile $zip): array
    {
        if (Str::lower($zip->getClientOriginalExtension()) !== 'zip') {
            throw new RuntimeException('Only ZIP files are allowed.');
        }

        if ($zip->getSize() > (int) config('cms.max_zip_size_bytes')) {
            throw new RuntimeException('ZIP too large.');
        }

        $tmpId = (string) Str::uuid();
        $staging = rtrim(config('cms.zip_tmp_path'), '/') . "/{$tmpId}";
        File::ensureDirectoryExists($staging);

        $stored = $zip->storeAs("cms/tmp/{$tmpId}", 'theme.zip');
        $zipAbs = storage_path('app/' . $stored);

        $archive = new ZipArchive();
        if ($archive->open($zipAbs) !== true) {
            throw new RuntimeException('Could not open ZIP.');
        }

        $this->validateZipEntries($archive);

        if (!$archive->extractTo($staging)) {
            $archive->close();
            throw new RuntimeException('ZIP extraction failed.');
        }
        $archive->close();

        $this->assertNoSymlinksAndNoTraversal($staging);

        $themeRoot = $this->detectThemeRoot($staging);

        // ✅ returns ThemeManifest DTO
        $manifest = $this->reader->read($themeRoot);
        $slug = $manifest->slug;

        $themesPath = rtrim(config('cms.themes_path'), '/');
        $target = "{$themesPath}/{$slug}";

        // backup existing
        if (File::isDirectory($target)) {
            $backup = "{$themesPath}/_backup_{$slug}_" . now()->format('Ymd_His');
            File::moveDirectory($target, $backup);
        }

        File::ensureDirectoryExists($themesPath);

        if (!File::moveDirectory($themeRoot, $target)) {
            throw new RuntimeException("Failed to move theme into {$target}");
        }

        // cleanup
        File::deleteDirectory($staging);

        // publish dist -> public/themes/{slug}/dist
        $this->publisher->publish($slug);

        // ✅ return array for UI
        return [
            'name' => $manifest->name,
            'slug' => $manifest->slug,
            'version' => $manifest->version,
            'author' => $manifest->author,
            'description' => $manifest->description,
            'parent' => $manifest->parent,
            'templates' => $manifest->templates,
            'menus' => $manifest->menus,
            'sidebars' => $manifest->sidebars,
            'assets' => $manifest->assets,
            'raw' => $manifest->raw,
        ];
    }

    private function validateZipEntries(ZipArchive $zip): void
    {
        $maxFiles = (int) config('cms.max_zip_files');
        if ($zip->numFiles > $maxFiles) {
            throw new RuntimeException("ZIP has too many files ({$zip->numFiles}).");
        }

        $total = 0;
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $stat = $zip->statIndex($i);
            if (!$stat)
                continue;

            $name = (string) $stat['name'];

            if (str_starts_with($name, '/') || str_contains($name, '../') || str_contains($name, '..\\')) {
                throw new RuntimeException('ZIP contains invalid paths.');
            }

            $total += (int) ($stat['size'] ?? 0);
            if ($total > (int) config('cms.max_uncompressed_bytes')) {
                throw new RuntimeException('ZIP uncompressed content too large.');
            }
        }
    }

    private function assertNoSymlinksAndNoTraversal(string $dir): void
    {
        $base = realpath($dir);
        if (!$base) {
            throw new RuntimeException('Invalid staging directory.');
        }

        foreach (File::allFiles($dir) as $file) {
            $path = $file->getPathname();

            if (is_link($path)) {
                throw new RuntimeException('Symlinks are not allowed in themes.');
            }

            $real = realpath($path);
            if ($real === false || !str_starts_with($real, $base . DIRECTORY_SEPARATOR)) {
                throw new RuntimeException('Path traversal detected.');
            }
        }
    }

    private function detectThemeRoot(string $staging): string
    {
        if (File::exists($staging . '/theme.json')) {
            return $staging;
        }

        foreach (File::directories($staging) as $dir) {
            if (File::exists($dir . '/theme.json')) {
                return $dir;
            }
        }

        throw new RuntimeException('theme.json not found in ZIP.');
    }
}