<?php

namespace App\Tests\Service\FinancialAnalysis;

use App\Entity\FinancialAnalysis\Transaction;
use App\Repository\FinancialAnalysis\ExpenseDraftRepository;
use App\Repository\FinancialAnalysis\TransactionRepository;
use App\Service\FinancialAnalysis\BudgetAdvService;
use App\Service\FinancialAnalysis\DraftNotificationService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class BudgetAdvServiceTest extends TestCase
{
    private TransactionRepository&MockObject $transactionRepo;
    private ExpenseDraftRepository&MockObject $expenseDraftRepo;
    private DraftNotificationService&MockObject $notificationService;
    private BudgetAdvService $service;

    protected function setUp(): void
    {
        $this->transactionRepo = $this->createMock(TransactionRepository::class);
        $this->expenseDraftRepo = $this->createMock(ExpenseDraftRepository::class);
        $this->notificationService = $this->createMock(DraftNotificationService::class);

        $this->service = new BudgetAdvService(
            $this->transactionRepo,
            $this->expenseDraftRepo,
            $this->notificationService
        );
    }

    public function testReturnCostArrayFromTransactions(): void
    {
        $tx1 = new Transaction();
        $tx1->setCost('100.50');

        $tx2 = new Transaction();
        $tx2->setCost('200.00');

        $tx3 = new Transaction();
        $tx3->setCost('300.75');

        $transactions = [$tx1, $tx2, $tx3];

        $result = $this->service->returnCostArrayFromTransactions($transactions);

        $this->assertIsArray($result);
        $this->assertCount(3, $result);
        $this->assertSame([100.50, 200.00, 300.75], $result);
    }

    public function testReturnCostArrayFromEmptyTransactions(): void
    {
        $result = $this->service->returnCostArrayFromTransactions([]);

        $this->assertIsArray($result);
        $this->assertCount(0, $result);
        $this->assertSame([], $result);
    }
}
