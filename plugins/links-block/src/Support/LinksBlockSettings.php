<?php

namespace Plugins\LinksBlock\Support;

use Illuminate\Support\Facades\Storage;

final class LinksBlockSettings
{
    public const SETTINGS_DIR = 'linksblock/settings';
    public const SETTINGS_FILE = self::SETTINGS_DIR . '/settings.json';

    public static function defaults(): array
    {
        return [
            // ✅ Match WP screenshot defaults
            'col' => 3,
            'hide' => 'yes',
            'new_window' => 'yes',
            'row' => 2,
            'rand' => 'yes',
            'n' => 60,
            'mcol' => 1,
            'tcol' => 2,
            'single_line' => 'yes',
        ];
    }

    public static function load(): array
    {
        $disk = Storage::disk('local');

        if (!$disk->exists(self::SETTINGS_FILE)) {
            return self::defaults();
        }

        $raw = $disk->get(self::SETTINGS_FILE);
        $arr = json_decode((string) $raw, true);

        if (!is_array($arr)) {
            return self::defaults();
        }

        return array_merge(self::defaults(), $arr);
    }

    public static function save(array $settings): void
    {
        $disk = Storage::disk('local');
        $disk->makeDirectory(self::SETTINGS_DIR);

        $d = self::defaults();

        $cleanInt = function ($v, int $min, int $max, int $fallback): int {
            $n = (int) $v;
            if ($n < $min) {
                return $fallback;
            }
            if ($n > $max) {
                return $max;
            }
            return $n;
        };

        $yesNo = function ($v, string $fallback = 'no'): string {
            $v = strtolower(trim((string) $v));
            return in_array($v, ['yes', 'no'], true) ? $v : $fallback;
        };

        $clean = [
            'col' => $cleanInt($settings['col'] ?? $d['col'], 1, 12, $d['col']),
            'hide' => $yesNo($settings['hide'] ?? $d['hide'], $d['hide']),
            'new_window' => $yesNo($settings['new_window'] ?? $d['new_window'], $d['new_window']),
            'row' => $cleanInt($settings['row'] ?? $d['row'], 0, 200, $d['row']),
            'rand' => $yesNo($settings['rand'] ?? $d['rand'], $d['rand']),
            'n' => $cleanInt($settings['n'] ?? $d['n'], 1, 5000, $d['n']),
            'mcol' => $cleanInt($settings['mcol'] ?? $d['mcol'], 1, 6, $d['mcol']),
            'tcol' => $cleanInt($settings['tcol'] ?? $d['tcol'], 1, 12, $d['tcol']),
            'single_line' => $yesNo($settings['single_line'] ?? $d['single_line'], $d['single_line']),
        ];

        $disk->put(
            self::SETTINGS_FILE,
            json_encode($clean, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
        );
    }
}