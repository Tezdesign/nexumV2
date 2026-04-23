<?php
namespace App\Service\FinancialAnalysis;

use App\Entity\FinancialAnalysis\ExpenseDraft;
use App\Entity\FinancialAnalysis\Transaction;
use App\Repository\FinancialAnalysis\BudgetProfileRepository;
use App\Repository\FinancialAnalysis\ExpenseDraftRepository;
use App\Repository\FinancialAnalysis\ProjectBudgetRepository;
use App\Repository\FinancialAnalysis\TransactionRepository;
use App\Repository\Projects\ProjectRepository;
use MathPHP\Statistics\Average;
use MathPHP\Statistics\Descriptive;
use PhpParser\Node\Expr\Array_;

class BudgetAdvService{
    public function __construct(
        private ProjectBudgetRepository $projectBudgetRepository,
        private BudgetProfileRepository $budgetProfileRepository,
        private TransactionRepository $transactionRepository,
        private ExpenseDraftRepository $expenseDraftRepository,
        private DraftNotificationService $notificationService){


    }

    public function returnCostArrayFromTransactions(array $transactions)
    {
        $costArray = [];
        foreach ($transactions as $transaction) {
            $costArray[] = $transaction->getCost();
        }

        return $costArray;
    }
    public function getZscoreForDraft(ExpenseDraft $expenseDraft){

        $Ref = $expenseDraft->getProjectBudgetRelated();
        $Sample=$this->transactionRepository->getTransactionsBasedonPB($Ref->getId());
        $costArray = $this->returnCostArrayFromTransactions($Sample);
        $n=count($Sample);




        if ($n<5){
            return ['status' => 'Pass', 'z_score' => null, 'reason' => 'Insufficient data baseline'];

        }
        $mean = Average::mean($costArray);
        $stdDev = Descriptive::standardDeviation($costArray);

        if ($stdDev == 0){
            if ($expenseDraft->getAmount() == $mean){
                return ['status' => 'Pass',
                        'z_score' => 0,
                        'reason' => 'Matches static history'];
            }
            return ['status' => 'Flagged',
                    'z_score' => 999,
                    'reason' => 'Breaks perfect historical pattern'];
        }
        $zScore = abs(($expenseDraft->getAmount() - $mean) / $stdDev);

        switch (true){
            case ($n >= 30 ):
                $limite=3.0;
                break;
            case ($n >= 15):
                $limite=3.5;
                break;
            default:
                $limite=4;
                break;
        }

        if ($limite > $zScore){
            return ['status' => 'Pass',
                    'z_score' => round($zScore, 2),
                    'reason' => 'Within standard historical variance'];
        }
        return ['status' => 'Flagged',
                'z_score' => round($zScore, 2),
                'reason' => 'Statistical anomaly detected'];

    }


    public function compareAgainstPB(ExpenseDraft $expenseDraft)
    {
        $projectbudget = $expenseDraft->getProjectBudgetRelated();
        $totalBudget = (float) $projectbudget->getTotalBudget();
        $actualSpend = (float) $projectbudget->getActualSpend();
        $remaining = $totalBudget - $actualSpend;
        $counter = (float) $expenseDraft->getAmount();

        if($counter > $remaining){
            return [
                'status' => 'Rejected',
                'reason_code' => 'INSUFFICIENT_FUNDS',
                'message' => "Draft amount ({$counter}) exceeds the absolute remaining budget ({$remaining}).",
                'metrics' => ['remaining' => $remaining]
            ];
        }

       switch (true){
           case ($actualSpend == 0):
               if ($counter > $totalBudget*0.35){
                   return [
                       'status' => 'Flagged',
                       'reason_code' => 'HIGH_INITIAL_CONCENTRATION',
                       'message' => "As the first transaction, the draft exceeds the 35% safety limit of the total budget.",
                       'metrics' => ['limit' => $totalBudget*0.35, 'draft_amount' => $counter]
                   ];
               }
               //
               break;
           default:
               $bigLimit = $totalBudget * 0.05;
               if ($counter > $remaining*0.25 && $counter > $bigLimit){
                   return [
                       'status' => 'Flagged',
                       'reason_code' => 'HIGH_REMAINING_CONCENTRATION',
                       'message' => "The draft consumes an unusually high percentage of the remaining budget.",
                       'metrics' => ['limit' => $remaining*0.25, 'draft_amount' => $counter, 'remaining' => $remaining] 
                   ];
               }
           break;

       }
        return [
            'status' => 'Pass',
            'reason_code' => 'WITHIN_CAPACITY',
            'message' => 'Draft passed capacity and concentration risk limits.'
        ];
    }

    public function detectDuplicateDraft(ExpenseDraft $expenseDraft): array
    {
        $budget = $expenseDraft->getProjectBudgetRelated();
        $amount = (float) $expenseDraft->getAmount();

        // Check for duplicates, but exclude the current draft if it's already saved (e.g. during an update)
        $duplicates = $this->expenseDraftRepository->findRecentDuplicates($budget->getId(), $amount);

        $realDuplicates = [];
        foreach ($duplicates as $dupe) {
            if ($expenseDraft->getId() !== null && $dupe->getId() === $expenseDraft->getId()) {
                continue; // Skip itself
            }
            $realDuplicates[] = $dupe;
        }

        if (count($realDuplicates) > 0) {
            return [
                'status' => 'Flagged',
                'reason_code' => 'POTENTIAL_DUPLICATE',
                'message' => "Found " . count($realDuplicates) . " recent draft(s) with the exact amount of {$amount} for this project.",
                'metrics' => [
                    'duplicate_count' => count($realDuplicates),
                    'timeframe_days' => 7
                ]
            ];
        }

        return [
            'status' => 'Pass',
            'reason_code' => 'UNIQUE_DRAFT',
            'message' => 'No recent duplicates detected.'
        ];
    }

    /**
     * Master function that orchestrates all automated checks for a new ExpenseDraft.
     * Evaluates Z-score, Budget constraints, and Duplicates, then determines the
     * final status (PASS, FLAGGED, or REJECTED) and saves a comprehensive
     * JSON report to the draft's evalData attribute.
     */
    public function evaluateDraft(ExpenseDraft $draft): void
    {
        $budgetEval = $this->compareAgainstPB($draft);
        $zScoreEval = $this->getZscoreForDraft($draft);
        $duplicateEval = $this->detectDuplicateDraft($draft);

        $finalStatus = 'PASS';

        if (isset($budgetEval['status']) && strtoupper($budgetEval['status']) === 'REJECTED') {
            $finalStatus = 'REJECTED';
        } elseif (
            (isset($budgetEval['status']) && strtoupper($budgetEval['status']) === 'FLAGGED') ||
            (isset($zScoreEval['status']) && strtoupper($zScoreEval['status']) === 'FLAGGED') ||
            (isset($duplicateEval['status']) && strtoupper($duplicateEval['status']) === 'FLAGGED')
        ) {
            $finalStatus = 'FLAGGED';
        }

        $evaluatedAt = (new \DateTimeImmutable())->format('Y-m-d H:i:s');
        $evalPayload = [
            'evaluated_at' => $evaluatedAt,
            'final_decision' => $finalStatus,
            'tests' => [
                'budget_capacity' => $budgetEval,
                'statistical_anomaly' => $zScoreEval,
                'duplicate_check' => $duplicateEval
            ]
        ];

        if ($finalStatus === 'REJECTED') {
            $reason = 'Auto-rejected by system: ' . ($budgetEval['message'] ?? 'Insufficient Funds');
            $evalPayload['rejection_data'] = [
                'reason' => $reason,
                'rejected_at' => $evaluatedAt,
                'rejected_by' => 'System (Auto)',
            ];
            
            $creatorId = $draft->getCreatedBy() ? $draft->getCreatedBy()->getId() : null;
            if ($creatorId) {
                $this->notificationService->addNotification($creatorId, 'Draft Auto-Rejected', 'Your draft "' . $draft->getSubject() . '" was automatically rejected: ' . $reason, 'error');
            }
        }

        $draft->setStatus($finalStatus);
        $draft->setEvalData($evalPayload);

        $this->expenseDraftRepository->save($draft, true);
    }
}