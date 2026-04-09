<?php

namespace App\Twig;

use App\Support\PlainTextSanitizer;
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

        $s = PlainTextSanitizer::toLine((string) $value);

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
