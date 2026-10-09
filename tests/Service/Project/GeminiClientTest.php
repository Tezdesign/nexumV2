<?php

namespace App\Tests\Service\Project;

use App\Service\Project\AI\GeminiClient;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class GeminiClientTest extends TestCase
{
    public function testApiKeyTravelsInAHeaderNotInTheUrl(): void
    {
        $seen = [];
        $http = new MockHttpClient(function (string $method, string $url, array $options) use (&$seen): MockResponse {
            $seen = ['url' => $url, 'headers' => $options['headers']];

            return new MockResponse(json_encode(['candidates' => [['content' => ['parts' => [['text' => '{"tasks":[]}']]]]]]));
        });

        (new GeminiClient($http, 'SECRET-KEY', 'gemini-2.5-flash'))
            ->suggestProjectTasks('Demo', null, new \DateTimeImmutable('2026-01-01'), new \DateTimeImmutable('2026-01-31'));

        $this->assertStringNotContainsString('SECRET-KEY', $seen['url']);
        $this->assertStringNotContainsString('key=', $seen['url']);
        $this->assertContains('x-goog-api-key: SECRET-KEY', $seen['headers']);
    }
}
