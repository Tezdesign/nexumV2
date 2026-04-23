<?php

namespace App\Service\FinancialAnalysis;

use App\Entity\FinancialAnalysis\BudgetProfile;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

class BudgetTrendCacheService
{
    private CacheInterface $cache;

    public function __construct(CacheInterface $cache)
    {
        $this->cache = $cache;
    }

    public function savePreUpdateState(BudgetProfile $profile, float $allocated, float $expenses): void
    {
        $id = $profile->getId();
        if (!$id) {
            return;
        }

        $cacheKey = 'budget_profile_state_' . $id;

        $disposable = (float) $profile->getBudgetDisposable();
        $remaining = $disposable - $expenses;
        $utilization = $disposable > 0 ? ($expenses / $disposable) * 100 : 0;
        $cashflow = $disposable - $allocated;

        $this->cache->delete($cacheKey);

        $this->cache->get($cacheKey, function (ItemInterface $item) use ($disposable, $expenses, $remaining, $utilization, $cashflow) {
            $item->expiresAfter(2592000); 

            return [
                'disposable' => $disposable,
                'expenses' => $expenses,
                'remaining' => $remaining,
                'utilization' => $utilization,
                'cashflow' => $cashflow,
                'timestamp' => time()
            ];
        });
    }

    public function calculateTrends(BudgetProfile $profile, float $currentAllocated, float $currentExpenses): array
    {
        $id = $profile->getId();
        if (!$id) {
            return $this->getEmptyTrendData();
        }

        $cacheKey = 'budget_profile_state_' . $id;

        $oldState = $this->cache->get($cacheKey, function (ItemInterface $item) use ($profile, $currentAllocated, $currentExpenses) {
            $item->expiresAfter(2592000);
            $disposable = (float) $profile->getBudgetDisposable();
            return [
                'disposable' => $disposable,
                'expenses' => $currentExpenses,
                'remaining' => $disposable - $currentExpenses,
                'utilization' => $disposable > 0 ? ($currentExpenses / $disposable) * 100 : 0,
                'cashflow' => $disposable - $currentAllocated,
                'timestamp' => time()
            ];
        });

        if (!isset($oldState['timestamp']) || $oldState['timestamp'] >= (time() - 2)) {
            return $this->getEmptyTrendData();
        }

        $currentDisposable = (float) $profile->getBudgetDisposable();
        $currentRemaining = $currentDisposable - $currentExpenses;
        $currentUtilization = $currentDisposable > 0 ? ($currentExpenses / $currentDisposable) * 100 : 0;
        $currentCashflow = $currentDisposable - $currentAllocated;

        $oldDisposable = $oldState['disposable'] ?? $oldState['budget_disposable'] ?? 0.0;
        $oldExpenses = $oldState['expenses'] ?? $oldState['total_expense'] ?? 0.0;
        $oldRemaining = $oldState['remaining'] ?? 0.0;
        $oldUtilization = $oldState['utilization'] ?? 0.0;
        $oldCashflow = $oldState['cashflow'] ?? 0.0;

        return [
            'budget' => $this->calculateVarianceData($currentDisposable, $oldDisposable, 'up-is-good'),
            'spending' => $this->calculateVarianceData($currentExpenses, $oldExpenses, 'down-is-good'),
            'remaining' => $this->calculateVarianceData($currentRemaining, $oldRemaining, 'up-is-good'),
            'utilization' => $this->calculateVarianceData($currentUtilization, $oldUtilization, 'down-is-good'),
            'cashflow' => ['badge' => '0%', 'direction' => 'up', 'class' => 'secondary', 'is_different' => false],
        ];
    }

    private function calculateVarianceData(float $current, float $old, string $mode): array
    {
        if ($old === 0.0) {
            $variance = ($current > 0) ? 100 : ($current < 0 ? -100 : 0);
        } else {
            $variance = (($current - $old) / $old) * 100;
        }

        $variance = round($variance, 1);
        $direction = $variance >= 0 ? 'up' : 'down';

        $class = 'secondary';
        if ($variance > 0) {
            $class = ($mode === 'up-is-good') ? 'success' : 'danger';
        } elseif ($variance < 0) {
            $class = ($mode === 'up-is-good') ? 'danger' : 'success';
        }

        return [
            'badge' => ($variance > 0 ? '+' : '') . $variance . '%',
            'direction' => $direction,
            'class' => $class,
            'is_different' => $variance !== 0.0
        ];
    }

    private function getEmptyTrendData(): array
    {
        $default = ['badge' => '0%', 'direction' => 'up', 'class' => 'secondary', 'is_different' => false];
        return [
            'budget' => $default,
            'spending' => $default,
            'remaining' => $default,
            'utilization' => $default,
            'cashflow' => $default
        ];
    }
}
