<?php

namespace App\Command;

use App\Repository\FinancialAnalysis\ProjectBudgetRepository;
use App\Repository\FinancialAnalysis\ExpenseDraftRepository;
use App\Service\FinancialAnalysis\OpenVinoAnalysisService;
use App\Service\FinancialAnalysis\DraftPolicyService;
use App\Service\FinancialAnalysis\ParseDraftIntentService;
use App\Service\FinancialAnalysis\BudgetAdvService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:test-nexum',
    description: 'Quick test for the Nexum AI local API.',
)]
class TestNexumCommand extends Command
{
    public function __construct(
        private ProjectBudgetRepository $projectBudgetRepository,
        private ExpenseDraftRepository $expenseDraftRepository,
        private OpenVinoAnalysisService $analysisService,
        private DraftPolicyService $policyService,
        private ParseDraftIntentService $intentService,
        private BudgetAdvService $budgetAdvService
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln("========================================");
        $output->writeln("   Nexum AI Engine Integration Test   ");
        $output->writeln("========================================");

        // 1. Test Project Budget Analysis
        $output->writeln("\n[1] Fetching ProjectBudget ID 12...");
        $budget = $this->projectBudgetRepository->find(12);
        
        if ($budget) {
            $output->writeln("✓ Found Budget: " . $budget->getName());
            $output->writeln("Sending to /api/nexum/analyze...");
            $analysisResult = $this->analysisService->analyzeProjectBudget($budget, 'Testing the AI API from CLI. Please provide a full, detailed analysis including variance, projected spending, probability of success, advice section, and predictions for the next 4-6 milestones.');
            
            // Try to parse the output nicely if it's JSON
            $decoded = json_decode($analysisResult, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                 $output->writeln("Response (Decoded):\n" . json_encode($decoded, JSON_PRETTY_PRINT));
            } else {
                 $output->writeln("Response (Raw):\n" . $analysisResult);
            }
        } else {
            $output->writeln("! ProjectBudget ID 12 not found in the database.");
        }

        // 2. Test Draft Policy Evaluation & Statistical Check
        $output->writeln("\n[2] Fetching ExpenseDraft ID 2...");
        $draft = $this->expenseDraftRepository->find(2);
        
        if ($draft) {
            $output->writeln("✓ Found Draft: " . $draft->getSubject() . " ($" . $draft->getAmount() . ")");
            
            // Modify draft for the test condition to trigger Rule 6 (Silly/Absurd test) and Rule 5 (Marketing > 1000)
            $draft->setDescription("test: I need money for beer to celebrate.");
            $draft->setAmount(5000);
            $draft->setCategory("MARKETING");
            
            $output->writeln("Modifying Draft Description to: " . $draft->getDescription());
            $output->writeln("Modifying Draft Amount to: $" . $draft->getAmount());
            
            $output->writeln("Passing to BudgetAdvService for full evaluation (AI + Stats)...");
            $this->budgetAdvService->evaluateDraft($draft);
            
            $output->writeln("Final Status: " . $draft->getStatus());
            $output->writeln("Evaluation Data:\n" . json_encode($draft->getEvalData(), JSON_PRETTY_PRINT));
            
            // In a real scenario, you wouldn't flush these test changes unless you want to save them.
        } else {
            $output->writeln("! ExpenseDraft ID 2 not found in the database.");
        }

        // 3. Test Intent Parsing
        $output->writeln("\n[3] Testing Intent Parsing...");
        $sampleText = "I need to subscribe to a $150 software service for Project Alpha.";
        $mockProjects = [['id' => 1, 'name' => 'Project Alpha'], ['id' => 12, 'name' => 'Marketing Campaign for APP']];
        $mockCategories = ['SOFTWARE', 'HARDWARE', 'TRAVEL'];
        
        $output->writeln("Input: \"$sampleText\"");
        $output->writeln("Sending to /api/nexum/intent...");
        $intentResult = $this->intentService->parseIntent($sampleText, $mockProjects, $mockCategories);
        $output->writeln("Response:\n" . json_encode($intentResult, JSON_PRETTY_PRINT));

        $output->writeln("\n========================================");
        $output->writeln("                 DONE                   ");
        $output->writeln("========================================");

        return Command::SUCCESS;
    }
}
