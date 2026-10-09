<?php

namespace App\Service;

use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/** Translates text with the free MyMemory API. Results are cached, a failure is never cached. */
class TranslatorService
{
    /** Languages the formation page offers. */
    public const LANGUAGES = ['fr', 'en', 'ar', 'es', 'de'];

    private const CHUNK_LENGTH = 400; // MyMemory refuses longer queries
    private const CACHE_SECONDS = 2592000; // 30 days: a description rarely changes
    private const REQUEST_TIMEOUT = 5.0;

    public function __construct(
        private readonly HttpClientInterface $client,
        private readonly CacheInterface $cache,
    ) {
    }

    public static function isSupported(string $language): bool
    {
        return in_array($language, self::LANGUAGES, true);
    }

    public function translate(string $text, string $from, string $to): string
    {
        $text = trim($text);
        if ($text === '' || $from === $to) {
            return $text;
        }

        return $this->cache->get('translation_' . sha1($from . '|' . $to . '|' . $text), function (ItemInterface $item) use ($text, $from, $to): string {
            [$translated, $complete] = $this->translateChunks(self::splitText($text, self::CHUNK_LENGTH), $from, $to);
            // If any piece fell back to the original text, try again on the next request.
            $item->expiresAfter($complete ? self::CACHE_SECONDS : 0);

            return $translated;
        });
    }

    /**
     * Cuts at spaces so a word (or a multi byte letter) is never split, each piece at most $maxLength characters.
     *
     * @return list<string>
     */
    public static function splitText(string $text, int $maxLength): array
    {
        $chunks = [];
        $current = '';
        foreach (preg_split('/(?<=\s)/u', $text) ?: [] as $word) {
            while (mb_strlen($word) > $maxLength) { // a single very long word
                if ($current !== '') {
                    $chunks[] = $current;
                    $current = '';
                }
                $chunks[] = mb_substr($word, 0, $maxLength);
                $word = mb_substr($word, $maxLength);
            }
            if (mb_strlen($current . $word) > $maxLength) {
                $chunks[] = $current;
                $current = '';
            }
            $current .= $word;
        }
        if ($current !== '') {
            $chunks[] = $current;
        }

        return $chunks;
    }

    /**
     * All requests are sent first and read afterwards, so the time is that of the slowest piece, not the sum.
     *
     * @param list<string> $chunks
     *
     * @return array{0: string, 1: bool} translated text and whether every piece was translated
     */
    private function translateChunks(array $chunks, string $from, string $to): array
    {
        $responses = [];
        foreach ($chunks as $index => $chunk) {
            $responses[$index] = $this->client->request('GET', 'https://api.mymemory.translated.net/get', [
                'query' => ['q' => $chunk, 'langpair' => $from . '|' . $to],
                'timeout' => self::REQUEST_TIMEOUT,
                'max_duration' => self::REQUEST_TIMEOUT * 2,
            ]);
        }

        $parts = [];
        $complete = true;
        foreach ($chunks as $index => $chunk) {
            try {
                $data = $responses[$index]->toArray();
                $translated = $data['responseData']['translatedText'] ?? null;
                // MyMemory answers 200 with a warning text when the daily quota is used up: check its own status.
                if (!is_string($translated) || trim($translated) === '' || (int) ($data['responseStatus'] ?? 200) !== 200) {
                    throw new \RuntimeException('Unusable translation');
                }
                $parts[] = trim($translated);
            } catch (\Throwable) {
                $complete = false;
                $parts[] = trim($chunk);
            }
        }

        return [implode(' ', $parts), $complete];
    }
}
