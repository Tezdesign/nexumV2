<?php
namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;

class TranslatorService
{
    public function __construct(private HttpClientInterface $client) {}

    public function translate(string $text, string $from, string $to): string
    {
        $chunks = $this->splitText($text, 400); // 🔥 max 400 safe
        $translatedText = '';

        foreach ($chunks as $chunk) {

            try {
                $response = $this->client->request('GET',
                    'https://api.mymemory.translated.net/get', [
                        'query' => [
                            'q' => $chunk,
                            'langpair' => "$from|$to"
                        ]
                    ]);

                $data = $response->toArray();

                $translatedText .= ($data['responseData']['translatedText'] ?? $chunk) . ' ';

            } catch (\Exception $e) {
                $translatedText .= $chunk . ' ';
            }
        }

        return trim($translatedText);
    }

    /**
     * @return array<int, string>
     */
    private function splitText(string $text, int $maxLength): array
    {
        if ($maxLength < 1) {
            $maxLength = 1;
        }

        return str_split($text, $maxLength);
    }
}
