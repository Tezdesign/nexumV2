<?php

namespace App\Service\Project\Calendar;

use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class PublicHolidayProvider
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly CacheInterface $cache,
        private readonly string $countryCode,
    ) {
    }

    public function getCountryCode(): string
    {
        return $this->countryCode;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getHolidays(?\DateTimeInterface $rangeStart = null, ?\DateTimeInterface $rangeEnd = null): array
    {
        $countryCode = strtoupper(trim($this->countryCode));
        if ($countryCode === '') {
            return [];
        }

        $years = $this->resolveYears($rangeStart, $rangeEnd);
        $holidays = [];

        foreach ($years as $year) {
            foreach ($this->fetchYear($countryCode, $year) as $holiday) {
                $dateValue = isset($holiday['date']) ? trim((string) $holiday['date']) : '';
                if ($dateValue === '') {
                    continue;
                }

                try {
                    $date = new \DateTimeImmutable($dateValue);
                } catch (\Throwable) {
                    continue;
                }

                if (!$this->isInRange($date, $rangeStart, $rangeEnd)) {
                    continue;
                }

                $holidays[] = [
                    'date' => $date,
                    'localName' => (string) ($holiday['localName'] ?? $holiday['name'] ?? 'Public holiday'),
                    'name' => (string) ($holiday['name'] ?? $holiday['localName'] ?? 'Public holiday'),
                    'countryCode' => $countryCode,
                    'global' => (bool) ($holiday['global'] ?? false),
                    'types' => array_values(array_filter(array_map('strval', (array) ($holiday['types'] ?? [])))),
                ];
            }
        }

        usort($holidays, static function (array $left, array $right): int {
            /** @var \DateTimeImmutable $leftDate */
            $leftDate = $left['date'];
            /** @var \DateTimeImmutable $rightDate */
            $rightDate = $right['date'];

            return $leftDate <=> $rightDate;
        });

        return $holidays;
    }

    /**
     * @return int[]
     */
    private function resolveYears(?\DateTimeInterface $rangeStart, ?\DateTimeInterface $rangeEnd): array
    {
        $start = $rangeStart ? (int) $rangeStart->format('Y') : (int) date('Y');
        $end = $rangeEnd ? (int) $rangeEnd->format('Y') : $start;

        if ($end < $start) {
            [$start, $end] = [$end, $start];
        }

        $years = [];
        for ($year = $start; $year <= $end; ++$year) {
            $years[] = $year;
        }

        return $years;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function fetchYear(string $countryCode, int $year): array
    {
        $cacheKey = sprintf('calendar_public_holidays_%s_%d', strtolower($countryCode), $year);

        return $this->cache->get($cacheKey, function (ItemInterface $item) use ($countryCode, $year): array {
            $item->expiresAfter(86400);

            try {
                $response = $this->httpClient->request('GET', sprintf(
                    'https://date.nager.at/api/v3/PublicHolidays/%d/%s',
                    $year,
                    rawurlencode($countryCode)
                ), [
                    'timeout' => 10,
                    'max_duration' => 15,
                ]);

                if ($response->getStatusCode() !== 200) {
                    return [];
                }

                $data = $response->toArray(false);

                return is_array($data) ? $data : [];
            } catch (\Throwable) {
                return [];
            }
        });
    }

    private function isInRange(
        \DateTimeInterface $date,
        ?\DateTimeInterface $rangeStart,
        ?\DateTimeInterface $rangeEnd,
    ): bool {
        $day = \DateTimeImmutable::createFromInterface($date)->setTime(0, 0);

        if ($rangeStart instanceof \DateTimeInterface) {
            $start = \DateTimeImmutable::createFromInterface($rangeStart)->setTime(0, 0);
            if ($day < $start) {
                return false;
            }
        }

        if ($rangeEnd instanceof \DateTimeInterface) {
            $end = \DateTimeImmutable::createFromInterface($rangeEnd)->setTime(0, 0);
            if ($day >= $end) {
                return false;
            }
        }

        return true;
    }
}
