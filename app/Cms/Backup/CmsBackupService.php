<?php

namespace App\Cms\Backup;

use App\Models\CmsBackup;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use ZipArchive;

class CmsBackupService
{
    public function create(?string $label = null): CmsBackup
    {
        $disk = (string) config('cms_backup.disk', 'local');
        $folder = trim((string) config('cms_backup.path', 'cms-backups'), '/');

        $ts = now()->format('Ymd_His');
        $file = "backup_{$ts}.zip";
        $diskPath = "{$folder}/{$file}";

        // Create in temp, then put into storage disk
        $tmp = tempnam(sys_get_temp_dir(), 'cms_backup_');
        if ($tmp === false) {
            throw new \RuntimeException('Cannot create temp file.');
        }
        @unlink($tmp);
        $tmpZip = $tmp . '.zip';

        $zip = new ZipArchive();
        if ($zip->open($tmpZip, ZipArchive::CREATE) !== true) {
            throw new \RuntimeException('Cannot open ZIP for writing.');
        }

        // 1) Export DB rows as JSON (WP-like “content export”)
        $data = $this->exportDatabaseAsArray();
        $zip->addFromString(
            'data/database.json',
            json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
        );

        // 2) Export app + cms meta
        $meta = [
            'app' => [
                'name' => config('app.name'),
                'url' => config('app.url'),
                'env' => config('app.env'),
            ],
            'created_at' => now()->toIso8601String(),
            'laravel' => app()->version(),
        ];
        $zip->addFromString(
            'data/meta.json',
            json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
        );

        // 3) Export public storage files (media)
        if (config('cms_backup.include_public_storage', true)) {
            $this->zipPublicStorage($zip);
        }

        $zip->close();

        $contents = file_get_contents($tmpZip);
        if ($contents === false) {
            @unlink($tmpZip);
            throw new \RuntimeException('Failed to read generated ZIP.');
        }

        Storage::disk($disk)->put($diskPath, $contents);
        @unlink($tmpZip);

        $fullPath = Storage::disk($disk)->path($diskPath);
        $size = (int) @filesize($fullPath);
        $sha1 = @sha1_file($fullPath) ?: null;

        return CmsBackup::query()->create([
            'disk' => $disk,
            'path' => $diskPath,
            'label' => $label,
            'size_bytes' => $size > 0 ? $size : 0,
            'sha1' => $sha1,
            'meta' => $meta,
            'created_by' => Auth::id(),
        ]);
    }

    /**
     * Restore “content-level backup” (DB JSON + public storage files).
     * WP-like: restores CMS content, not your entire Laravel app code.
     */
    public function restore(CmsBackup $backup, bool $truncate = true): void
    {
        $disk = $backup->disk;
        $zipBytes = Storage::disk($disk)->get($backup->path);

        $tmp = tempnam(sys_get_temp_dir(), 'cms_restore_');
        if ($tmp === false) {
            throw new \RuntimeException('Cannot create temp file for restore.');
        }
        @unlink($tmp);
        $tmpZip = $tmp . '.zip';

        file_put_contents($tmpZip, $zipBytes);

        $zip = new ZipArchive();
        if ($zip->open($tmpZip) !== true) {
            @unlink($tmpZip);
            throw new \RuntimeException('Cannot open backup ZIP.');
        }

        $dbJson = $zip->getFromName('data/database.json');
        if ($dbJson === false) {
            $zip->close();
            @unlink($tmpZip);
            throw new \RuntimeException('Backup missing data/database.json');
        }

        $payload = json_decode($dbJson, true);
        if (!is_array($payload)) {
            $zip->close();
            @unlink($tmpZip);
            throw new \RuntimeException('Invalid database.json format.');
        }

        $tables = (array) config('cms_backup.tables', []);

        // ✅ IMPORTANT: avoid TRUNCATE inside transactions (MySQL implicit commit)
        // We'll do a "content restore": DELETE rows then re-insert.
        DB::statement('SET FOREIGN_KEY_CHECKS=0');

        try {
            if ($truncate) {
                // Delete existing content (safe for transaction + avoids implicit commit)
                // Use reverse order to be extra safe even if FK checks are toggled later
                foreach (array_reverse($tables) as $t) {
                    if (!Schema::hasTable($t)) {
                        continue;
                    }
                    DB::table($t)->delete();
                }
            }

            // Insert backup rows (wrap only INSERTS in a transaction)
            DB::transaction(function () use ($payload, $tables) {
                foreach ($tables as $t) {
                    if (!Schema::hasTable($t)) {
                        continue;
                    }

                    $rows = $payload[$t] ?? [];
                    if (!is_array($rows) || $rows === []) {
                        continue;
                    }

                    foreach (array_chunk($rows, 500) as $chunk) {
                        DB::table($t)->insert($chunk);
                    }
                }
            });
        } finally {
            DB::statement('SET FOREIGN_KEY_CHECKS=1');
        }

        // Restore public storage
        $this->restorePublicStorageFromZip($zip);

        $zip->close();
        @unlink($tmpZip);
    }


    public function prune(): int
    {
        $keep = (int) config('cms_backup.keep_last', 14);
        if ($keep <= 0) {
            return 0;
        }

        $toDelete = CmsBackup::query()
            ->orderByDesc('id')
            ->skip($keep)
            ->take(500)
            ->get();

        $count = 0;

        foreach ($toDelete as $b) {
            Storage::disk($b->disk)->delete($b->path);
            $b->delete();
            $count++;
        }

        return $count;
    }

    // ---------------------------------------------------------
    // Export helpers (FIX: supports tables without `id`)
    // ---------------------------------------------------------

    private function exportDatabaseAsArray(): array
    {
        $tables = (array) config('cms_backup.tables', []);
        $out = [];

        foreach ($tables as $t) {
            if (!Schema::hasTable($t)) {
                $out[$t] = [];
                continue;
            }

            $out[$t] = $this->exportTableRows($t);
        }

        return $out;
    }

    private function exportTableRows(string $table): array
    {
        $query = DB::table($table);

        $orderCols = $this->orderColumnsFor($table);
        foreach ($orderCols as $col) {
            if (Schema::hasColumn($table, $col)) {
                $query->orderBy($col);
            }
        }

        return $query->get()->map(fn($r) => (array) $r)->all();
    }

    /**
     * Order by:
     * 1) id (if exists)
     * 2) else primary key columns (composite PK supported)
     * 3) else empty => no ordering
     */
    private function orderColumnsFor(string $table): array
    {
        if (Schema::hasColumn($table, 'id')) {
            return ['id'];
        }

        try {
            $db = DB::getDatabaseName();

            $cols = DB::table('information_schema.KEY_COLUMN_USAGE')
                ->where('TABLE_SCHEMA', $db)
                ->where('TABLE_NAME', $table)
                ->where('CONSTRAINT_NAME', 'PRIMARY')
                ->orderBy('ORDINAL_POSITION')
                ->pluck('COLUMN_NAME')
                ->map(fn($c) => (string) $c)
                ->all();

            return $cols ?: [];
        } catch (\Throwable $e) {
            // If information_schema isn't accessible, just don't order.
            return [];
        }
    }

    // ---------------------------------------------------------
    // Storage zip helpers
    // ---------------------------------------------------------

    private function zipPublicStorage(ZipArchive $zip): void
    {
        $base = storage_path('app/public');

        if (!is_dir($base)) {
            return;
        }

        $rii = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($base, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($rii as $file) {
            /** @var \SplFileInfo $file */
            if (!$file->isFile()) {
                continue;
            }
            $abs = $file->getPathname();
            $rel = ltrim(str_replace($base, '', $abs), DIRECTORY_SEPARATOR);

            $zip->addFile($abs, 'storage/public/' . str_replace('\\', '/', $rel));
        }
    }

    private function restorePublicStorageFromZip(ZipArchive $zip): void
    {
        $targetBase = storage_path('app/public');
        if (!is_dir($targetBase)) {
            @mkdir($targetBase, 0775, true);
        }

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if (!is_string($name)) {
                continue;
            }

            if (!str_starts_with($name, 'storage/public/')) {
                continue;
            }

            $rel = substr($name, strlen('storage/public/'));
            $rel = str_replace(['..', '\\'], ['', '/'], $rel);

            $contents = $zip->getFromIndex($i);
            if ($contents === false) {
                continue;
            }

            $dest = $targetBase . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel);
            $dir = dirname($dest);
            if (!is_dir($dir)) {
                @mkdir($dir, 0775, true);
            }

            file_put_contents($dest, $contents);
        }
    }
}