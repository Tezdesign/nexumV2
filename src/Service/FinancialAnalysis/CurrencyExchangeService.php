<?php

namespace App\Service\FinancialAnalysis;
use App\Repository\FinancialAnalysis\BudgetProfileRepository;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

class CurrencyExchangeService
{
    public function __construct(
        #[Autowire('%env(CURRENCY_API_KEY)%')]
        private string $apiKey,
        private BudgetProfileRepository $budgetProfileRepository,
    ) {}

    public function fetchRatesForProfile(int $profileId): string
    {
        $profile = $this->budgetProfileRepository->find($profileId);
        $baseCurrency = $profile ? $profile->getBaseCurrency() : 'TND';

        $url = "https://v6.exchangerate-api.com/v6/{$this->apiKey}/latest/{$baseCurrency}";

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        $response = curl_exec($ch);
        $curlError = curl_error($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        // DUMP AND DIE: This forces everything to the screen and stops the app
        dd([
            '1_attempted_url' => $url,
            '2_http_status_code' => $httpCode,
            '3_curl_internal_error' => $curlError,
            '4_raw_api_response' => $response
        ]);

        return $response;
    }
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