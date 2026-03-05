<?php

namespace Plugins\LinksBlock\Support;

final class LinksBlockShortcode
{
    public static function render(array $atts = []): string
    {
        $defaults = LinksBlockSettings::load();

        // attribute helpers
        $int = function ($key, int $min, int $max, int $fallback) use ($atts, $defaults): int {
            $v = $atts[$key] ?? $defaults[$key] ?? $fallback;
            $n = (int) $v;
            if ($n < $min)
                return $fallback;
            if ($n > $max)
                return $max;
            return $n;
        };

        $yes = function ($key, string $fallback = 'no') use ($atts, $defaults): string {
            $v = $atts[$key] ?? $defaults[$key] ?? $fallback;
            $v = strtolower(trim((string) $v));
            return $v === 'yes' ? 'yes' : 'no';
        };

        $col = $int('col', 1, 12, 4);
        $row = $int('row', 0, 200, 5);
        $mcol = $int('mcol', 1, 6, 2);
        $tcol = $int('tcol', 1, 12, 3);
        $n = $int('n', 1, 5000, 60);

        $rand = $yes('rand');
        $hide = $yes('hide');
        $newWindow = $yes('new-window') === 'yes' ? true : ($yes('new_window') === 'yes'); // accept both
        $singleLine = $yes('single-line') === 'yes' ? true : ($yes('single_line') === 'yes');

        $links = LinksBlockLinkProvider::allLinks();

        if ($rand === 'yes') {
            shuffle($links);
        }

        // total limit
        $totalWanted = $n;
        if ($row > 0) {
            $totalWanted = min($totalWanted, $col * $row);
        }

        $links = array_slice($links, 0, $totalWanted);

        // Build columns like WP behavior:
        // - if row > 0 => fixed rows per column
        // - else => distribute evenly
        $columns = [];

        if ($row > 0) {
            for ($i = 0; $i < $col; $i++) {
                $chunk = array_slice($links, $i * $row, $row);
                if ($chunk === [])
                    break;
                $columns[] = $chunk;
            }
        } else {
            if ($col <= 1) {
                $columns = [$links];
            } else {
                $perCol = (int) ceil(max(1, count($links)) / $col);
                $columns = array_chunk($links, $perCol);
            }
        }

        return view('links-block::shortcodes.linksblock', [
            'columns' => $columns,
            'mcol' => $mcol,
            'tcol' => $tcol,
            'col' => $col,
            'row' => $row, // ✅ ADD THIS LINE
            'hide' => $hide === 'yes',
            'newWindow' => $newWindow,
            'singleLine' => $singleLine,
        ])->render();
    }

    public static function titleFromUrl(string $url): string
    {
        $u = trim($url);
        if ($u === '')
            return 'Link';

        // mimic WP plugin style
        $path = parse_url($u, PHP_URL_PATH);
        $path = is_string($path) ? $path : $u;

        $base = basename(rtrim($path, '/'));
        $base = pathinfo($base, PATHINFO_FILENAME);

        $base = str_replace(['-', '_'], ' ', $base);
        $base = preg_replace('/\s+/', ' ', $base) ?? $base;
        $base = trim($base);

        return $base !== '' ? ucwords($base) : 'Link';
    }
}