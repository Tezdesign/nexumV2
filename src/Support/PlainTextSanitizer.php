<?php

namespace App\Support;

/**
 * Strips HTML / script and decodes entities so user content is stored and handled as plain text only.
 */
final class PlainTextSanitizer
{
    /**
     * Single-line text (titles, names): no HTML, whitespace collapsed.
     */
    public static function toLine(string $value): string
    {
        $s = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $s = strip_tags($s);
        $s = preg_replace('/\s+/u', ' ', $s) ?? $s;

        return trim($s);
    }

    /**
     * Multi-line text (descriptions): no HTML; newlines preserved, normalized.
     */
    public static function toBlock(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $s = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $s = strip_tags($s);
        $s = str_replace(["\r\n", "\r"], "\n", $s);
        $s = preg_replace("/\n{3,}/u", "\n\n", $s) ?? $s;

        $lines = explode("\n", $s);
        $lines = array_map(static function (string $line): string {
            $line = preg_replace('/[ \t]+/u', ' ', $line) ?? $line;

            return trim($line);
        }, $lines);
        $s = implode("\n", $lines);
        $s = trim($s);

        return $s === '' ? null : $s;
    }
}
