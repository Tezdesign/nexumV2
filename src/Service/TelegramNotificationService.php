<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class TelegramNotificationService
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string $botToken = '',
        private readonly string $defaultChatId = '',
    ) {
    }

    public function sendMessage(string $chatId, string $text): bool
    {
        if (trim($this->botToken) === '') {
            return false;
        }

        try {
            $response = $this->httpClient->request('POST', $this->getApiUrl(), [
                'json' => [
                    'chat_id' => $chatId,
                    'text' => $text,
                ],
            ]);

            return $response->getStatusCode() === 200;
        } catch (TransportExceptionInterface|\Throwable) {
            return false;
        }
    }

    public function notifyNewReclamation(string $fullName, string $email, string $title): bool
    {
        if (trim($this->defaultChatId) === '') {
            return false;
        }

        $message = "Nouvelle reclamation soumise\n"
            . "Utilisateur: {$fullName}\n"
            . "Email: {$email}\n"
            . "Titre: {$title}";

        return $this->sendMessage($this->defaultChatId, $message);
    }

    private function getApiUrl(): string
    {
        return 'https://api.telegram.org/bot' . $this->botToken . '/sendMessage';
    }
}
