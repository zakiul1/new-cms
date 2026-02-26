<?php

namespace Plugins\LinkMate;

use App\Cms\Core\SettingsRepository;

class LinkMate
{
    public const SETTINGS_GROUP = 'plugin:linkmate';

    public function __construct(private SettingsRepository $settings)
    {
    }

    public function isTypeEnabled(string $type): bool
    {
        $enabled = $this->settings->get(self::SETTINGS_GROUP, 'enabled_types', []);
        $enabled = is_array($enabled) ? $enabled : [];

        return in_array($type, $enabled, true);
    }

    /**
     * Apply LinkMate to an HTML string for a given type (post/page/media/custom).
     */
    public function applyToHtml(string $html, string $type): string
    {
        $html = (string) $html;
        if (trim($html) === '') {
            return $html;
        }

        if (!$this->isTypeEnabled($type)) {
            return $html;
        }

        $keywordsText = (string) $this->settings->get(self::SETTINGS_GROUP, 'keywords', '');
        $urlsText = (string) $this->settings->get(self::SETTINGS_GROUP, 'urls', '');

        $keywords = $this->linesToList($keywordsText);
        $urls = $this->linesToList($urlsText);

        if ($keywords === [] || $urls === []) {
            return $html;
        }

        $maxLinks = (int) $this->settings->get(self::SETTINGS_GROUP, 'max_links', 3);
        $wordGap = (int) $this->settings->get(self::SETTINGS_GROUP, 'word_gap', 10);
        $maxKeywordUses = (int) $this->settings->get(self::SETTINGS_GROUP, 'max_keyword_uses', 3);

        $allowInBold = (bool) $this->settings->get(self::SETTINGS_GROUP, 'allow_in_bold', true);
        $allowInHeadings = (bool) $this->settings->get(self::SETTINGS_GROUP, 'allow_in_headings', false);

        $maxLinks = max(0, $maxLinks);
        $wordGap = max(0, $wordGap);
        $maxKeywordUses = max(0, $maxKeywordUses);

        if ($maxLinks === 0) {
            return $html;
        }

        // -----------------------------------------
        // 1) Protect blocked sections
        //    Always protect existing <a> tags.
        // -----------------------------------------
        [$workHtml, $blocked] = $this->protectSections($html, $allowInBold, $allowInHeadings);

        // -----------------------------------------
        // 2) Insert links (WP-like workflow)
        //    - random keyword
        //    - random url (not 1:1 mapping)
        //    - one replacement per loop
        // -----------------------------------------
        $linkedPositions = [];     // word indexes used
        $keywordCounts = [];       // keyword usage counts
        $linked = 0;
        $attempts = 0;
        $maxAttempts = 600;        // safety guard

        while ($linked < $maxLinks && $attempts < $maxAttempts) {
            $attempts++;

            $kw = $keywords[array_rand($keywords)];
            $url = $urls[array_rand($urls)];

            if ($kw === '' || $url === '') {
                continue;
            }

            $kwKey = mb_strtolower($kw);

            if ($maxKeywordUses > 0 && (($keywordCounts[$kwKey] ?? 0) >= $maxKeywordUses)) {
                continue;
            }

            // Find a valid occurrence that satisfies word-gap
            $match = $this->findOccurrenceWithGap($workHtml, $kw, $linkedPositions, $wordGap);

            if (!$match) {
                continue;
            }

            [$matchText, $offset] = $match;

            $anchor = '<a href="' . e($url) . '" target="_blank" rel="noopener">' . $matchText . '</a>';

            // Replace ONLY this occurrence at offset
            $workHtml = substr($workHtml, 0, $offset)
                . $anchor
                . substr($workHtml, $offset + strlen($matchText));

            // Track usage
            $wordPos = $this->wordIndexAtOffset($workHtml, $offset);
            if ($wordPos !== null) {
                $linkedPositions[] = $wordPos;
            }

            $keywordCounts[$kwKey] = ($keywordCounts[$kwKey] ?? 0) + 1;
            $linked++;
        }

        // -----------------------------------------
        // 3) Restore blocked sections
        // -----------------------------------------
        return $this->restoreSections($workHtml, $blocked);
    }

    private function linesToList(string $text): array
    {
        $lines = preg_split("/\r\n|\n|\r/", (string) $text) ?: [];
        $out = [];

        foreach ($lines as $line) {
            $line = trim((string) $line);
            if ($line !== '') {
                $out[] = $line;
            }
        }

        return array_values($out);
    }

    /**
     * Protect:
     * - <a> always
     * - <b>/<strong> if allow_in_bold = false
     * - <h1..h6> if allow_in_headings = false
     *
     * Returns [html, blockedMap]
     */
    private function protectSections(string $html, bool $allowInBold, bool $allowInHeadings): array
    {
        $blocked = [];
        $i = 0;

        $patterns = [
            '#<a\b[^>]*>.*?</a>#is', // always protect anchors
        ];

        if (!$allowInBold) {
            $patterns[] = '#<(b|strong)\b[^>]*>.*?</\1>#is';
        }

        if (!$allowInHeadings) {
            $patterns[] = '#<h[1-6]\b[^>]*>.*?</h[1-6]>#is';
        }

        foreach ($patterns as $pattern) {
            $html = preg_replace_callback($pattern, function ($m) use (&$blocked, &$i) {
                $key = '__LM_BLOCK_' . $i . '__';
                $blocked[$key] = $m[0];
                $i++;
                return $key;
            }, $html) ?? $html;
        }

        return [$html, $blocked];
    }

    private function restoreSections(string $html, array $blocked): string
    {
        if ($blocked === []) {
            return $html;
        }

        // Replace placeholders back
        return strtr($html, $blocked);
    }

    /**
     * Find a keyword occurrence (whole-word-ish) in HTML that satisfies gap rule.
     * Returns [matchedText, offset] or null.
     */
    private function findOccurrenceWithGap(string $html, string $keyword, array $linkedPositions, int $gap): ?array
    {
        $kw = preg_quote($keyword, '#');

        // unicode-safe-ish "word boundary":
        // not preceded/followed by letter/number/underscore
        $pattern = '#(?<![\pL\pN_])(' . $kw . ')(?![\pL\pN_])#iu';

        if (!preg_match_all($pattern, $html, $m, PREG_OFFSET_CAPTURE)) {
            return null;
        }

        // Try occurrences in order; pick first that satisfies word gap
        foreach ($m[1] as $cap) {
            $text = (string) ($cap[0] ?? '');
            $offset = (int) ($cap[1] ?? -1);

            if ($offset < 0 || $text === '') {
                continue;
            }

            if ($gap <= 0 || $linkedPositions === []) {
                return [$text, $offset];
            }

            $wordPos = $this->wordIndexAtOffset($html, $offset);
            if ($wordPos === null) {
                return [$text, $offset];
            }

            $ok = true;
            foreach ($linkedPositions as $p) {
                if (abs((int) $p - $wordPos) < $gap) {
                    $ok = false;
                    break;
                }
            }

            if ($ok) {
                return [$text, $offset];
            }
        }

        return null;
    }

    /**
     * Approximate word index (count of words before offset).
     */
    private function wordIndexAtOffset(string $html, int $offset): ?int
    {
        if ($offset < 0) {
            return null;
        }

        $before = substr($html, 0, $offset);
        $plain = strip_tags($before);
        $plain = html_entity_decode($plain, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        // PHP counts "words" mainly latin; good enough for same behavior
        return str_word_count($plain);
    }
}