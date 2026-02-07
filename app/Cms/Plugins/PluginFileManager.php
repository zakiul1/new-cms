<?php

namespace App\Cms\Plugins;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use RuntimeException;

class PluginFileManager
{
    /**
     * Only allow these folders/files to be read/written.
     * This is CRITICAL for security.
     */
    private array $allowedRoots = [
        'bootstrap.php',
        'plugin.json',
        'src',
        'resources',
        'routes',
        'views',
        'dist',
    ];

    private array $blockedNames = [
        '.env',
        '.git',
        'vendor',
        'node_modules',
        'storage',
        'bootstrap/cache',
    ];

    public function pluginBasePath(string $slug): string
    {
        $slug = trim($slug);

        if ($slug === '') {
            throw new RuntimeException('Missing plugin slug.');
        }

        // ✅ allow only safe folder names: letters, numbers, dash, underscore, dot
        if (!preg_match('/^[A-Za-z0-9._-]+$/', $slug)) {
            throw new RuntimeException("Invalid plugin slug: {$slug}");
        }

        $base = base_path('plugins/' . $slug);

        if (!is_dir($base)) {
            throw new RuntimeException("Plugin directory not found: {$slug}");
        }

        return $base;
    }

    /**
     * @return array<int, array{path:string,label:string,ext:string,editable:bool,binary:bool,mime:string}>
     */
    /**
     * @return array<int, array{path:string,label:string,ext:string,editable:bool,binary:bool,mime:string}>
     */
    public function tree(string $slug): array
    {
        $base = $this->pluginBasePath($slug);

        /** @var array<string, array{path:string,label:string,ext:string,editable:bool,binary:bool,mime:string}> $byPath */
        $byPath = [];

        foreach ($this->allowedRoots as $root) {
            $abs = $base . DIRECTORY_SEPARATOR . $root;

            if (is_file($abs)) {
                $meta = $this->metaFromAbsolute($base, $abs);
                if ($meta) {
                    $byPath[$meta['path']] = $meta;
                }
                continue;
            }

            if (is_dir($abs)) {
                foreach (File::allFiles($abs) as $f) {
                    $meta = $this->metaFromAbsolute($base, $f->getPathname());
                    if ($meta) {
                        $byPath[$meta['path']] = $meta;
                    }
                }
            }
        }

        $files = array_values($byPath);

        // Sort by path for stable UI
        usort($files, fn($a, $b) => strcmp($a['path'], $b['path']));

        return $files;
    }



    public function read(string $slug, string $relativePath): string
    {
        $abs = $this->resolveAllowedPath($slug, $relativePath);

        if (!is_file($abs)) {
            throw new RuntimeException("File not found: {$relativePath}");
        }

        // prevent reading huge files in editor
        if (filesize($abs) > 1024 * 1024 * 2) { // 2MB
            throw new RuntimeException("File too large to open in editor.");
        }

        if ($this->isBinaryFile($abs)) {
            return ''; // binary files are previewed, not opened as text
        }

        return (string) File::get($abs);
    }

    public function write(string $slug, string $relativePath, string $content): void
    {
        $abs = $this->resolveAllowedPath($slug, $relativePath);

        if (!is_file($abs)) {
            throw new RuntimeException("File not found: {$relativePath}");
        }

        if ($this->isBinaryFile($abs)) {
            throw new RuntimeException("Binary files cannot be edited.");
        }

        // backup
        $bak = $abs . '.bak.' . now()->format('YmdHis');
        File::copy($abs, $bak);

        // if php file -> lint before saving
        $ext = strtolower(pathinfo($abs, PATHINFO_EXTENSION));
        if ($ext === 'php') {
            $tmp = $abs . '.tmp.' . uniqid();
            File::put($tmp, $content);

            $result = Process::run(['php', '-l', $tmp]);

            File::delete($tmp);

            if (!$result->successful()) {
                throw new RuntimeException("PHP lint failed:\n" . $result->errorOutput());
            }
        }

        File::put($abs, $content);
    }

    public function mime(string $slug, string $relativePath): string
    {
        $abs = $this->resolveAllowedPath($slug, $relativePath);
        return $this->guessMime($abs);
    }

    public function isBinary(string $slug, string $relativePath): bool
    {
        $abs = $this->resolveAllowedPath($slug, $relativePath);
        return $this->isBinaryFile($abs);
    }

    public function absolutePath(string $slug, string $relativePath): string
    {
        return $this->resolveAllowedPath($slug, $relativePath);
    }

    public function diff(string $old, string $new): string
    {
        // simple line diff (good enough for preview)
        $a = explode("\n", $old);
        $b = explode("\n", $new);

        $out = [];
        $max = max(count($a), count($b));

        for ($i = 0; $i < $max; $i++) {
            $la = $a[$i] ?? null;
            $lb = $b[$i] ?? null;

            if ($la === $lb) {
                continue;
            }

            if ($la !== null)
                $out[] = "- " . $la;
            if ($lb !== null)
                $out[] = "+ " . $lb;
        }

        return $out ? implode("\n", $out) : "No changes.";
    }

    private function metaFromAbsolute(string $base, string $abs): ?array
    {
        // ✅ Hide editor backup files (example: dist/plugin.js.bak.20260127113536)
        if (preg_match('/\.bak\.\d{14}$/', $abs)) {
            return null;
        }

        $rel = ltrim(str_replace($base, '', $abs), DIRECTORY_SEPARATOR);
        $rel = str_replace('\\', '/', $rel);

        // Handle blade.php correctly
        $lowerRel = strtolower($rel);
        $ext = strtolower(pathinfo($abs, PATHINFO_EXTENSION));

        $isBlade = str_ends_with($lowerRel, '.blade.php');
        $effectiveExt = $isBlade ? 'blade.php' : $ext;

        $mime = $this->guessMime($abs);
        $binary = $this->isBinaryFile($abs);

        $editableExt = in_array($effectiveExt, [
            'php',
            'blade.php',
            'js',
            'ts',
            'json',
            'css',
            'scss',
            'vue',
            'md',
            'txt',
            'html',
        ], true);

        return [
            'path' => $rel,
            'label' => $rel,
            'ext' => $effectiveExt,
            'editable' => !$binary && $editableExt,
            'binary' => $binary,
            'mime' => $mime,
        ];
    }


    private function resolveAllowedPath(string $slug, string $relativePath): string
    {
        $base = $this->pluginBasePath($slug);

        $relativePath = str_replace('\\', '/', trim($relativePath));
        $relativePath = ltrim($relativePath, '/');

        if ($relativePath === '' || str_contains($relativePath, '..')) {
            throw new RuntimeException('Invalid file path.');
        }

        // block dangerous names anywhere in the path
        foreach ($this->blockedNames as $blocked) {
            if (str_contains($relativePath, $blocked)) {
                throw new RuntimeException('Path is blocked.');
            }
        }

        // allowlist root (must start with allowed root)
        $allowed = false;
        foreach ($this->allowedRoots as $root) {
            if ($relativePath === $root || str_starts_with($relativePath, rtrim($root, '/') . '/')) {
                $allowed = true;
                break;
            }
        }

        if (!$allowed) {
            throw new RuntimeException('This path is not editable/servable.');
        }

        $abs = realpath($base . DIRECTORY_SEPARATOR . $relativePath) ?: ($base . DIRECTORY_SEPARATOR . $relativePath);

        // ensure still inside plugin base
        $baseReal = realpath($base) ?: $base;
        $absReal = realpath($abs) ?: $abs;

        if (!str_starts_with($absReal, $baseReal)) {
            throw new RuntimeException('Path escapes plugin directory.');
        }

        return $absReal;
    }

    private function isBinaryFile(string $abs): bool
    {
        $ext = strtolower(pathinfo($abs, PATHINFO_EXTENSION));

        // treat common binaries as binary
        if (in_array($ext, ['png', 'jpg', 'jpeg', 'gif', 'webp', 'ico', 'pdf', 'zip', 'woff', 'woff2', 'ttf', 'eot'], true)) {
            return true;
        }

        $chunk = @file_get_contents($abs, false, null, 0, 2048);
        if ($chunk === false)
            return true;

        // null byte is typical binary indicator
        return str_contains($chunk, "\0");
    }

    private function guessMime(string $abs): string
    {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        if (!$finfo)
            return 'application/octet-stream';
        $mime = finfo_file($finfo, $abs) ?: 'application/octet-stream';
        finfo_close($finfo);
        return $mime;
    }
}