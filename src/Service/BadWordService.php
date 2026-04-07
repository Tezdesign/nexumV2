<?php
// src/Service/BadWordService.php
namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;

class BadWordService
{
    public function __construct(private HttpClientInterface $client) {}

    public function clean(string $text): string
    {
        try {
            $response = $this->client->request('GET',
                'https://www.purgomalum.com/service/plain', [
                    'query' => ['text' => $text]
                ]);

            return $response->getContent();

        } catch (\Exception $e) {
            return $text;
        }
    }
}