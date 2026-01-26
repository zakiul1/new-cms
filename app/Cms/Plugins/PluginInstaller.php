<?php

namespace App\Cms\Plugins;

use Illuminate\Support\Facades\File;
use RuntimeException;
use ZipArchive;

class PluginInstaller
{
    public function installFromZip(string $zipAbsolutePath): string
    {
        if (!is_file($zipAbsolutePath)) {
            throw new RuntimeException('ZIP file not found.');
        }

        $zip = new ZipArchive();
        if ($zip->open($zipAbsolutePath) !== true) {
            throw new RuntimeException('Could not open ZIP.');
        }

        // Find plugin.json anywhere in the zip (handles zip root folder)
        $pluginJsonIndex = null;
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = (string) $zip->getNameIndex($i);
            if (str_ends_with(str_replace('\\', '/', $name), 'plugin.json')) {
                $pluginJsonIndex = $i;
                break;
            }
        }

        if ($pluginJsonIndex === null) {
            $zip->close();
            throw new RuntimeException('plugin.json not found in ZIP.');
        }

        $raw = $zip->getFromIndex($pluginJsonIndex);
        $manifest = json_decode((string) $raw, true);

        if (!is_array($manifest)) {
            $zip->close();
            throw new RuntimeException('Invalid plugin.json (not JSON).');
        }

        $slug = (string) ($manifest['slug'] ?? '');
        if ($slug === '' || !preg_match('/^[a-z0-9\-]+$/', $slug)) {
            $zip->close();
            throw new RuntimeException('Invalid plugin slug. Use lowercase letters, numbers, hyphen.');
        }

        // Extract safely into a temp folder first
        $tmpBase = storage_path('app/tmp/plugin-install');
        File::ensureDirectoryExists($tmpBase);

        $tmpDir = $tmpBase . DIRECTORY_SEPARATOR . $slug . '-' . uniqid();
        File::ensureDirectoryExists($tmpDir);

        // Secure extraction (block path traversal)
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = (string) $zip->getNameIndex($i);
            $normalized = str_replace('\\', '/', $name);

            if (str_contains($normalized, '../') || str_starts_with($normalized, '/')) {
                $zip->close();
                File::deleteDirectory($tmpDir);
                throw new RuntimeException('ZIP contains unsafe paths.');
            }
        }

        if (!$zip->extractTo($tmpDir)) {
            $zip->close();
            File::deleteDirectory($tmpDir);
            throw new RuntimeException('Failed to extract ZIP.');
        }

        $zip->close();

        // Determine actual plugin root in extracted folder
        // If zip has root folder, plugin.json will be inside it.
        $pluginJsonPath = $this->findFileRecursive($tmpDir, 'plugin.json');
        if (!$pluginJsonPath) {
            File::deleteDirectory($tmpDir);
            throw new RuntimeException('Extracted plugin.json not found.');
        }

        $pluginRoot = dirname($pluginJsonPath);

        // Final destination
        $dest = base_path('plugins' . DIRECTORY_SEPARATOR . $slug);
        File::ensureDirectoryExists(base_path('plugins'));

        // Backup old plugin if exists
        if (is_dir($dest)) {
            $backupBase = storage_path('app/plugin-backups');
            File::ensureDirectoryExists($backupBase);
            $backupDir = $backupBase . DIRECTORY_SEPARATOR . $slug . '-' . now()->format('YmdHis');
            File::moveDirectory($dest, $backupDir);
        }

        // Move plugin root into final destination
        File::moveDirectory($pluginRoot, $dest);

        // cleanup temp
        File::deleteDirectory($tmpDir);

        return $slug;
    }

    private function findFileRecursive(string $dir, string $filename): ?string
    {
        foreach (File::allFiles($dir) as $file) {
            if ($file->getFilename() === $filename) {
                return $file->getPathname();
            }
        }
        return null;
    }
}
