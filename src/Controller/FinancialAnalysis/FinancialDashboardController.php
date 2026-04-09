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
            'projects' => array_slice($projects, 0, 6), // Show only top 6 on dashboard
            'form' => $form->createView(),
            'projectBudgetForm' => $projectBudgetForm->createView(),
            'kpi_budget_value' => number_format($budgetVal / 1000, 1) . 'k',
            'kpi_spending_value' => number_format($totals['expenses'] / 1000, 1) . 'k',
            'kpi_remaining_value' => number_format($remainingVal / 1000, 1) . 'k',
            'kpi_utilization_value' => $utilizationPercent . '%',
            'kpi_cashflow_value' => number_format(($budgetVal - $totals['allocated']) / 1000, 1) . 'k',
        ]);
    }

    #[Route('/profile/{id}/projects', name: 'apps-financial-analysis-profile-projects')]
    public function allProjects(
        BudgetProfile $budgetProfile, 
        BudgetDashboardService $dashboardService, 
        Request $request, 
        ProjectBudgetRepository $projectBudgetRepository
    ): Response {
        $projectBudget = new ProjectBudget();
        if ($budgetProfile->getStartDate() && $budgetProfile->getEndDate()) {
            $projectBudget->setTransientFiscalStart($budgetProfile->getStartDate());
            $projectBudget->setTransientFiscalEnd($budgetProfile->getEndDate());
        }

        $projectBudgetForm = $this->createForm(ProjectBudgetType::class, $projectBudget, [
            'fiscal_start' => $budgetProfile->getStartDate(),
            'fiscal_end' => $budgetProfile->getEndDate(),
        ]);
        $projectBudgetForm->handleRequest($request);

        if ($projectBudgetForm->isSubmitted() && $projectBudgetForm->isValid()) {
            $projectBudgetRepository->save($projectBudget, true);
            $this->addFlash('success', 'Project Budget created successfully!');
            return $this->redirectToRoute('apps-financial-analysis-profile-projects', ['id' => $budgetProfile->getId()]);
        }

        $projects = [];
        if ($budgetProfile->getStartDate() && $budgetProfile->getEndDate()) {
            $filteredBudgets = $projectBudgetRepository->findByFiscalYearScope($budgetProfile->getStartDate(), $budgetProfile->getEndDate());
            foreach ($filteredBudgets as $pb) {
                $projects[] = $dashboardService->formatBudgetDetails($pb);
            }
        }

        return $this->render('financial-analysis/all_projects.html.twig', [
            'budgetProfile' => $budgetProfile,
            'projects' => $projects,
            'projectBudgetForm' => $projectBudgetForm->createView(),
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

        $searchTerm = $request->query->get('q');
        $transactions = $dashboardService->formatTransactions($originalBudget, $searchTerm);

        if ($request->headers->get('X-Requested-With') === 'XMLHttpRequest') {
            return $this->render('financial-analysis/FA_components/_transaction_list_ajax.html.twig', [
                'transactions' => $transactions,
            ]);
        }

        return $this->render('financial-analysis/budget_details.html.twig', [
            'projectBudget' => $dashboardService->formatBudgetDetails($originalBudget),
            'projectBudgetEntity' => $originalBudget,
            'budgetProfile' => $profile, // Pass the Profile object for breadcrumbs
            'projectBudgetForm' => $projectBudgetForm->createView(),
            'transactionForm' => $transactionForm->createView(),
            'transactions' => $transactions,
        ]);
    }

    #[Route('/transaction/{id}/update', name: 'apps-financial-analysis-update-transaction', methods: ['POST'])]
    public function updateTransaction(
        Transaction $transaction, 
        Request $request, 
        BudgetDashboardService $dashboardService
    ): Response {
        $projectBudget = $transaction->getProjectBudget();
        $profile = $dashboardService->getFiscalProfileForBudget($projectBudget);
        
        $form = $this->createForm(TransactionType::class, $transaction);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $dashboardService->handleTransactionUpdateCascade($projectBudget, $transaction, $profile);
            $this->addFlash('success', 'Transaction updated successfully!');
        } else {
            $this->addFlash('danger', 'Failed to update transaction. Please check errors.');
        }

        return $this->redirectToRoute('apps-financial-analysis-budget-details', ['id' => $projectBudget->getId()]);
    }

    #[Route('/budget/{id}/transactions/bulk-delete', name: 'apps-financial-analysis-bulk-delete-transactions', methods: ['POST'])]
    public function bulkDeleteTransactions(
        ProjectBudget $projectBudget,
        Request $request,
        BudgetDashboardService $dashboardService
    ): Response {
        $idsString = $request->request->get('transaction_ids');
        if ($idsString) {
            $ids = explode(',', $idsString);
            $profile = $dashboardService->getFiscalProfileForBudget($projectBudget);
            
            $dashboardService->handleBulkDeleteCascade($projectBudget, $ids, $profile);
            $this->addFlash('success', count($ids) . ' transactions deleted successfully!');
        }

        return $this->redirectToRoute('apps-financial-analysis-budget-details', ['id' => $projectBudget->getId()]);
    }

    #[Route('/budget/{id}/delete', name: 'apps-financial-analysis-delete-project-budget', methods: ['POST'])]
    public function deleteProjectBudget(
        ProjectBudget $projectBudget,
        BudgetDashboardService $dashboardService
    ): Response {
        $profile = $dashboardService->getFiscalProfileForBudget($projectBudget);
        $dashboardService->handleProjectBudgetDeletionCascade($projectBudget, $profile);
        
        $this->addFlash('success', 'Project Budget and all associated transactions deleted successfully!');
        
        if ($profile) {
            return $this->redirectToRoute('apps-financial-analysis-profile', ['id' => $profile->getId()]);
        }
        
        return $this->redirectToRoute('apps-financial-analysis-landing');
    }

    #[Route('/profile/{id}/delete', name: 'apps-financial-analysis-delete-profile', methods: ['POST'])]
    public function deleteBudgetProfile(
        BudgetProfile $budgetProfile,
        BudgetDashboardService $dashboardService
    ): Response {
        $dashboardService->handleFullFiscalYearDeletionCascade($budgetProfile);
        $this->addFlash('success', 'Fiscal Year Profile and all associated project budgets deleted successfully!');
        return $this->redirectToRoute('apps-financial-analysis-landing');
    }
}
