<?php
namespace App\Service\Sms;

use Symfony\Contracts\HttpClient\HttpClientInterface;

class InfobipSmsService
{
    public function __construct(
        private HttpClientInterface $client,
        private string $apiKey,
        private string $baseUrl,
        private string $sender
    ) {}

    /**
     * @return array{statusCode: int, body: string}
     */
    public function sendSms(string $to, string $message): array
    {
        $response = $this->client->request('POST', rtrim($this->baseUrl, '/') . '/sms/3/messages', [
            'headers' => [
                'Authorization' => 'App ' . $this->apiKey,
                'Accept' => 'application/json',
            ],
            'json' => [
                'messages' => [
                    [
                        'destinations' => [
                            ['to' => $to]
                        ],
                        'sender' => $this->sender,
                        'content' => [
                            'text' => $message
                        ]
                    ]
                ]
            ]
        ]);

        return [
            'statusCode' => $response->getStatusCode(),
            'body' => $response->getContent(false),
        ];
    }
}
