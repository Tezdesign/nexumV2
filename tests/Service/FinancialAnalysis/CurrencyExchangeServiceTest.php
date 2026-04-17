<?php

namespace App\Tests\Service\FinancialAnalysis;
use App\Service\FinancialAnalysis\CurrencyExchangeService;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class CurrencyExchangeServiceTest extends KernelTestCase
{
    public function testFetchRatesForProfileReturnsValidJson(): void
    {

        self::bootKernel();
        $container = static::getContainer();

        // 2. Grab your fully autowired service directly from the container
        /** @var CurrencyExchangeService $currencyService */
        $currencyService = $container->get(CurrencyExchangeService::class);


        $jsonResponse = $currencyService->fetchRatesForProfile(11);


        $data = json_decode($jsonResponse, true);


        $this->assertIsArray($data, 'The response should decode into a valid PHP array.');
        $this->assertArrayHasKey('result', $data, 'The API response must contain a "result" key.');


        $this->assertEquals('success', $data['result'], 'The API call failed or the key is invalid.');


        $this->assertArrayHasKey('conversion_rates', $data, 'The response is missing the conversion rates data.');
    }
}