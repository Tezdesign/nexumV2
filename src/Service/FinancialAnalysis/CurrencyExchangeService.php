<?php

namespace App\Service\FinancialAnalysis;

use App\Repository\FinancialAnalysis\BudgetProfileRepository;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class CurrencyExchangeService
{
    public function __construct(
        #[Autowire(env: 'CURRENCY_API_KEY')]
        private string $apiKey,
        private BudgetProfileRepository $budgetProfileRepository,
        private HttpClientInterface $httpClient,
        private LoggerInterface $logger,
    ) {}

    /** The provider's JSON answer, or '{}' when the call fails (the caller then shows an empty list). */
    public function fetchRatesForProfile(int $profileId): string
    {
        $profile = $this->budgetProfileRepository->find($profileId);
        $baseCurrency = strtoupper(trim((string) ($profile?->getBaseCurrency() ?? 'TND')));
        if (preg_match('/^[A-Z]{3}$/', $baseCurrency) !== 1) {
            return '{}';
        }

        try {
            // The key goes in a header, not in the URL, so it never lands in logs or error pages.
            $response = $this->httpClient->request('GET', 'https://v6.exchangerate-api.com/v6/latest/' . $baseCurrency, [
                'auth_bearer' => $this->apiKey,
                'timeout' => 10,
            ]);

            return $response->getStatusCode() === 200 ? $response->getContent() : $this->failed('HTTP ' . $response->getStatusCode());
        } catch (\Throwable $e) {
            return $this->failed($e->getMessage());
        }
    }

    private function failed(string $reason): string
    {
        $this->logger->warning('Exchange rate call failed', ['reason' => $reason]);

        return '{}';
    }



    /**
     * @return array<int, array{text: string, children: array<int, array{id: string, text: string, rate: mixed}>}>
     */
    public function parseAndGroupRatesForSelect(string $rawJson): array
    {
        $data = json_decode($rawJson, true);

        if (!isset($data['result']) || $data['result'] !== 'success' || !isset($data['conversion_rates'])) {
            return [];
        }

        $rates = $data['conversion_rates'];

        $targetCurrenciesMap = [
            'North America' => [
                'USD' => 'US Dollar',
                'CAD' => 'Canadian Dollar',
            ],
            'Europe' => [
                'EUR' => 'Euro',
                'GBP' => 'British Pound',
                'CHF' => 'Swiss Franc',
            ],
            'Asia & Oceania' => [
                'JPY' => 'Japanese Yen',
                'CNY' => 'Chinese Yuan',
                'AUD' => 'Australian Dollar',
            ],
            'Africa' => [
                'TND' => 'Tunisian Dinar',
                'ZAR' => 'South African Rand',
            ]
        ];

        $select2Data = [];

        foreach ($targetCurrenciesMap as $continent => $currencies) {
            $children = [];

            foreach ($currencies as $code => $name) {
                if (isset($rates[$code])) {
                    $children[] = [
                        'id' => $code,
                        'text' => "$code - $name",
                        'rate' => $rates[$code]
                    ];
                }
            }

            if (!empty($children)) {
                $select2Data[] = [
                    'text' => $continent,
                    'children' => $children
                ];
            }
        }

        return $select2Data;
    }


}
