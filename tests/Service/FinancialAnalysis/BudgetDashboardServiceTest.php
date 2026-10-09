<?php

namespace App\Tests\Service\FinancialAnalysis;

use App\Entity\FinancialAnalysis\BudgetProfile;
use App\Entity\FinancialAnalysis\ProjectBudget;
use App\Entity\FinancialAnalysis\Transaction;
use App\Repository\FinancialAnalysis\BudgetProfileRepository;
use App\Repository\FinancialAnalysis\ProjectBudgetRepository;
use App\Repository\FinancialAnalysis\TransactionRepository;
use App\Service\FinancialAnalysis\BudgetDashboardService;
use App\Service\FinancialAnalysis\BudgetTrendCacheService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Doctrine\Common\Collections\ArrayCollection;

class BudgetDashboardServiceTest extends TestCase
{
    private ProjectBudgetRepository&MockObject $projectBudgetRepo;
    private BudgetProfileRepository&MockObject $budgetProfileRepo;
    private TransactionRepository&MockObject $transactionRepo;
    private BudgetTrendCacheService&MockObject $trendCacheService;
    private BudgetDashboardService $service;

    protected function setUp(): void
    {
        $this->projectBudgetRepo = $this->createMock(ProjectBudgetRepository::class);
        $this->budgetProfileRepo = $this->createMock(BudgetProfileRepository::class);
        $this->transactionRepo = $this->createMock(TransactionRepository::class);
        $this->trendCacheService = $this->createMock(BudgetTrendCacheService::class);

        $this->service = new BudgetDashboardService(
            $this->projectBudgetRepo,
            $this->budgetProfileRepo,
            $this->transactionRepo,
            $this->trendCacheService
        );
    }

    public function testFormatTransactionsWithoutSearchTerm(): void
    {
        $budget = new ProjectBudget();
        
        $tx1 = new Transaction();
        $tx1->setId(1);
        $tx1->setReference('TX-123456');
        $tx1->setExpenseCategory('Hardware');
        $tx1->setCost('100.50');
        $tx1->setDateStamp(new \DateTime('2025-01-01'));
        $tx1->setDescription('Test hardware');

        $budget->addTransaction($tx1);

        $result = $this->service->formatTransactions($budget);

        $this->assertCount(1, $result);
        $this->assertSame(1, $result[0]['id']);
        $this->assertSame('TX-123456', $result[0]['reference']);
        $this->assertSame('Hardware', $result[0]['expenseCategory']);
        $this->assertSame('100.50', $result[0]['cost']);
        $this->assertSame('2025-01-01', $result[0]['dateStamp']);
        $this->assertSame('Test hardware', $result[0]['description']);
    }

    public function testFormatTransactionsWithSearchTerm(): void
    {
        $budget = $this->createMock(ProjectBudget::class);
        $budget->method('getId')->willReturn(1);

        $tx1 = new Transaction();
        $tx1->setId(2);
        $tx1->setReference('TX-654321');
        $tx1->setExpenseCategory('Software');
        $tx1->setCost('200.00');
        $tx1->setDateStamp(new \DateTime('2025-02-02'));
        $tx1->setDescription('Test software search');

        $this->transactionRepo->expects($this->once())
            ->method('searchByReferenceOrDescriptionDql')
            ->with(1, 'software')
            ->willReturn([$tx1]);

        $result = $this->service->formatTransactions($budget, 'software');

        $this->assertCount(1, $result);
        $this->assertSame(2, $result[0]['id']);
        $this->assertSame('TX-654321', $result[0]['reference']);
    }

    public function testHandleTransactionCascade(): void
    {
        $budget = $this->createMock(ProjectBudget::class);
        $budget->method('getId')->willReturn(1);
        $budget->expects($this->once())->method('setActualSpend')->with('500.00');
        $budget->expects($this->once())->method('calculateStatus');

        $transaction = new Transaction();
        
        $profile = new BudgetProfile();
        $profile->setStartDate(new \DateTime('2025-01-01'));
        $profile->setEndDate(new \DateTime('2025-12-31'));

        $this->projectBudgetRepo->expects($this->exactly(2))
            ->method('getTotalsForFiscalYear')
            ->willReturn(['allocated' => 1000.0, 'expenses' => 500.0]);

        $this->trendCacheService->expects($this->once())
            ->method('savePreUpdateState');

        $this->transactionRepo->expects($this->once())
            ->method('save')
            ->with($transaction, true);

        $this->transactionRepo->expects($this->once())
            ->method('getTotalCostForProjectBudget')
            ->with(1)
            ->willReturn(500.0);

        $this->projectBudgetRepo->expects($this->once())
            ->method('updateActualSpendAndStatusDql')
            ->with($budget);

        $this->budgetProfileRepo->expects($this->once())
            ->method('updateTotalExpenseDql')
            ->with($profile, 500.0);

        $this->service->handleTransactionCascade($budget, $transaction, $profile);
    }

    public function testHandleTransactionUpdateCascade(): void
    {
        $budget = $this->createMock(ProjectBudget::class);
        $budget->method('getId')->willReturn(2);
        $budget->expects($this->once())->method('setActualSpend')->with('600.00');
        $budget->expects($this->once())->method('calculateStatus');

        $transaction = new Transaction();

        $this->transactionRepo->expects($this->once())
            ->method('updateTransactionDql')
            ->with($transaction);

        $this->transactionRepo->expects($this->once())
            ->method('getTotalCostForProjectBudget')
            ->with(2)
            ->willReturn(600.0);

        $this->projectBudgetRepo->expects($this->once())
            ->method('updateActualSpendAndStatusDql')
            ->with($budget);

        // No profile passed this time
        $this->service->handleTransactionUpdateCascade($budget, $transaction, null);
    }

    public function testHandleBulkDeleteCascade(): void
    {
        $budget = $this->createMock(ProjectBudget::class);
        $budget->method('getId')->willReturn(3);
        $budget->expects($this->once())->method('setActualSpend')->with('300.00');
        $budget->expects($this->once())->method('calculateStatus');

        $ids = ['10', '11', 'abc', '-4', '0', '10', '1; DROP'];

        // Only clean, distinct, positive ids reach the query, scoped to this budget.
        $this->transactionRepo->expects($this->once())
            ->method('bulkDeleteDql')
            ->with([10, 11], 3)
            ->willReturn(2);

        $this->transactionRepo->expects($this->once())
            ->method('getTotalCostForProjectBudget')
            ->with(3)
            ->willReturn(300.0);

        $this->projectBudgetRepo->expects($this->once())
            ->method('updateActualSpendAndStatusDql')
            ->with($budget);

        // No profile passed
        $this->assertSame(2, $this->service->handleBulkDeleteCascade($budget, $ids, null));
    }
}
