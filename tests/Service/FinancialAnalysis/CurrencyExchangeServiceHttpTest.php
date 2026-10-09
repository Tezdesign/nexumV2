<?php

namespace App\Tests\Service\FinancialAnalysis;

use App\Entity\FinancialAnalysis\BudgetProfile;
use App\Repository\FinancialAnalysis\BudgetProfileRepository;
use App\Service\FinancialAnalysis\CurrencyExchangeService;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class CurrencyExchangeServiceHttpTest extends TestCase
{
    /** @var array<int, array{string, array<string, mixed>}> */
    private array $calls = [];

    private function service(MockResponse $response, string $currency = 'eur'): CurrencyExchangeService
    {
        $profile = $this->createMock(BudgetProfile::class);
        $profile->method('getBaseCurrency')->willReturn($currency);
        $repo = $this->createMock(BudgetProfileRepository::class);
        $repo->method('find')->willReturn($profile);
        $client = new MockHttpClient(function (string $method, string $url, array $options) use ($response): MockResponse {
            $this->calls[] = [$url, $options];

            return $response;
        });

        return new CurrencyExchangeService('SECRET-KEY', $repo, $client, new NullLogger());
    }

    public function testKeyTravelsInAHeaderNotInTheUrl(): void
    {
        $json = '{"result":"success","conversion_rates":{"USD":1.1}}';
        $this->assertSame($json, $this->service(new MockResponse($json))->fetchRatesForProfile(1));

        [$url, $options] = $this->calls[0];
        $this->assertSame('https://v6.exchangerate-api.com/v6/latest/EUR', $url);
        $this->assertStringNotContainsString('SECRET-KEY', $url);
        $this->assertContains('Authorization: Bearer SECRET-KEY', $options['headers']);
    }

    public function testHttpErrorGivesAnEmptyAnswer(): void
    {
        $this->assertSame('{}', $this->service(new MockResponse('{"result":"error"}', ['http_code' => 403]))->fetchRatesForProfile(1));
    }

    public function testNetworkFailureGivesAnEmptyAnswer(): void
    {
        $this->assertSame('{}', $this->service(new MockResponse('', ['error' => 'timeout']))->fetchRatesForProfile(1));
    }

    public function testOddCurrencyCodeIsNeverSent(): void
    {
        $this->assertSame('{}', $this->service(new MockResponse('{}'), '../x')->fetchRatesForProfile(1));
        $this->assertSame([], $this->calls);
    }
}
