<?php

namespace App\Controller\FinancialAnalysis;

use App\Entity\FinancialAnalysis\BudgetProfile;
use App\Entity\FinancialAnalysis\ProjectBudget;
use App\Entity\FinancialAnalysis\Transaction;
use App\Form\FinancialAnalysis\BudgetProfileType;
use App\Form\FinancialAnalysis\ProjectBudgetType;
use App\Form\FinancialAnalysis\TransactionType;
use App\Repository\FinancialAnalysis\BudgetProfileRepository;
use App\Repository\FinancialAnalysis\ProjectBudgetRepository;
use App\Repository\FinancialAnalysis\TransactionRepository;
use App\Service\FinancialAnalysis\BudgetDashboardService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/apps-financial-analysis')]
class FinancialDashboardController extends AbstractController
{
    #[Route('', name: 'apps-financial-analysis-landing')]
    public function index(Request $request, EntityManagerInterface $entityManager, BudgetProfileRepository $budgetProfileRepository): Response
    {
        $budgetProfile = new BudgetProfile();
        $form = $this->createForm(BudgetProfileType::class, $budgetProfile);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($budgetProfile);
            $entityManager->flush();

            $this->addFlash('success', 'Budget Profile created successfully!');

            return $this->redirectToRoute('apps-financial-analysis-landing');
        }

        $budgetProfiles = $budgetProfileRepository->findAll();

        return $this->render('financial-analysis/landing.html.twig', [
            'budgetProfiles' => $budgetProfiles,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/profile/{id}', name: 'apps-financial-analysis-profile')]
    public function overview(
        BudgetProfile $budgetProfile, 
        BudgetDashboardService $dashboardService, 
        Request $request, 
        EntityManagerInterface $entityManager, 
        ProjectBudgetRepository $projectBudgetRepository
    ): Response {
        $originalProfile = clone $budgetProfile;

        $form = $this->createForm(BudgetProfileType::class, $budgetProfile);
        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            if ($budgetProfile->getStartDate() && $budgetProfile->getEndDate()) {
                $totals = $projectBudgetRepository->getTotalsForFiscalYear($budgetProfile->getStartDate(), $budgetProfile->getEndDate());
                $budgetProfile->setTransientAllocatedBudgets($totals['allocated']);
                $budgetProfile->setTransientProjectExpenses($totals['expenses']);
            }
            
            if ($form->isValid()) {
                $entityManager->flush();
                $this->addFlash('success', 'Budget Profile updated successfully!');
                return $this->redirectToRoute('apps-financial-analysis-profile', ['id' => $budgetProfile->getId()]);
            }
        }

        $projectBudget = new ProjectBudget();
        if ($originalProfile->getStartDate() && $originalProfile->getEndDate()) {
            $projectBudget->setTransientFiscalStart($originalProfile->getStartDate());
            $projectBudget->setTransientFiscalEnd($originalProfile->getEndDate());
        }

        $projectBudgetForm = $this->createForm(ProjectBudgetType::class, $projectBudget, [
            'fiscal_start' => $originalProfile->getStartDate(),
            'fiscal_end' => $originalProfile->getEndDate(),
        ]);
        $projectBudgetForm->handleRequest($request);

        if ($projectBudgetForm->isSubmitted() && $projectBudgetForm->isValid()) {
            $projectBudgetRepository->save($projectBudget, true);
            $this->addFlash('success', 'Project Budget created successfully!');
            return $this->redirectToRoute('apps-financial-analysis-profile', ['id' => $budgetProfile->getId()]);
        }

        $projects = [];
        if ($originalProfile->getStartDate() && $originalProfile->getEndDate()) {
            $filteredBudgets = $projectBudgetRepository->findByFiscalYearScope($originalProfile->getStartDate(), $originalProfile->getEndDate());
            foreach ($filteredBudgets as $pb) {
                $projects[] = $dashboardService->formatBudgetDetails($pb);
            }
        }
        
        $budgetVal = (float) $originalProfile->getBudgetDisposable();
        $totals = $originalProfile->getStartDate() && $originalProfile->getEndDate() 
            ? $projectBudgetRepository->getTotalsForFiscalYear($originalProfile->getStartDate(), $originalProfile->getEndDate()) 
            : ['allocated' => 0.0, 'expenses' => 0.0];
        
        $remainingVal = $budgetVal - $totals['expenses'];
        $utilizationPercent = $budgetVal > 0 ? round(($totals['expenses'] / $budgetVal) * 100, 1) : 0;

        return $this->render('financial-analysis/overview.html.twig', [
            'budgetProfile' => $originalProfile,
            'projects' => $projects,
            'form' => $form->createView(),
            'projectBudgetForm' => $projectBudgetForm->createView(),
            'kpi_budget_value' => number_format($budgetVal / 1000, 1) . 'k',
            'kpi_spending_value' => number_format($totals['expenses'] / 1000, 1) . 'k',
            'kpi_remaining_value' => number_format($remainingVal / 1000, 1) . 'k',
            'kpi_utilization_value' => $utilizationPercent . '%',
            'kpi_cashflow_value' => number_format(($budgetVal - $totals['allocated']) / 1000, 1) . 'k',
        ]);
    }

    #[Route('/budget/{id}', name: 'apps-financial-analysis-budget-details')]
    public function budgetDetails(
        ProjectBudget $projectBudget, 
        Request $request, 
        BudgetDashboardService $dashboardService,
        ProjectBudgetRepository $projectBudgetRepository
    ): Response {
        $originalBudget = clone $projectBudget;
        $profile = $dashboardService->getFiscalProfileForBudget($projectBudget);

        $fStart = $profile ? $profile->getStartDate() : null;
        $fEnd = $profile ? $profile->getEndDate() : null;

        if ($fStart && $fEnd) {
            $projectBudget->setTransientFiscalStart($fStart);
            $projectBudget->setTransientFiscalEnd($fEnd);
        }

        $projectBudgetForm = $this->createForm(ProjectBudgetType::class, $projectBudget, [
            'fiscal_start' => $fStart,
            'fiscal_end' => $fEnd,
        ]);
        $projectBudgetForm->handleRequest($request);

        if ($projectBudgetForm->isSubmitted() && $projectBudgetForm->isValid()) {
            $projectBudget->calculateStatus();
            $projectBudgetRepository->updateBudgetDql($projectBudget);
            $this->addFlash('success', 'Project Budget updated successfully!');
            return $this->redirectToRoute('apps-financial-analysis-budget-details', ['id' => $projectBudget->getId()]);
        }

        $transaction = new Transaction();
        $transactionForm = $this->createForm(TransactionType::class, $transaction);
        $transactionForm->handleRequest($request);

        if ($transactionForm->isSubmitted() && $transactionForm->isValid()) {
            $transaction->setProjectBudget($projectBudget);
            $dashboardService->handleTransactionCascade($projectBudget, $transaction, $profile);
            $this->addFlash('success', 'Transaction added successfully! Spending updated.');
            return $this->redirectToRoute('apps-financial-analysis-budget-details', ['id' => $projectBudget->getId()]);
        }

        return $this->render('financial-analysis/budget_details.html.twig', [
            'projectBudget' => $dashboardService->formatBudgetDetails($originalBudget),
            'projectBudgetEntity' => $originalBudget,
            'projectBudgetForm' => $projectBudgetForm->createView(),
            'transactionForm' => $transactionForm->createView(),
            'transactions' => $dashboardService->formatTransactions($originalBudget),
        ]);
    }
}
