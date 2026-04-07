<?php

namespace App\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

final class TextSanitizerExtension extends AbstractExtension
{
    public function getFilters(): array
    {
        return [
            new TwigFilter('plain_text', [$this, 'plainText']),
        ];
    }

    /**
     * Strips HTML tags and normalizes whitespace so DB content never renders as HTML or shows raw tags.
     */
    public function plainText(mixed $value, int $maxLen = 0): string
    {
        if ($value === null) {
            return '';
        }

        $s = (string) $value;
        $s = html_entity_decode($s, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $s = strip_tags($s);
        $s = preg_replace('/\\s+/u', ' ', $s) ?? $s;
        $s = trim($s);

        if ($maxLen > 0) {
            $len = function_exists('mb_strlen') ? mb_strlen($s, 'UTF-8') : strlen($s);
            if ($len > $maxLen) {
                $cut = function_exists('mb_substr') ? mb_substr($s, 0, $maxLen, 'UTF-8') : substr($s, 0, $maxLen);
                $s = rtrim($cut) . '...';
            }
        }

        return $s;
    }
}
