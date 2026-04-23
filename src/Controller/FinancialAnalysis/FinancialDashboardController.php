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
    public function index(Request $request, EntityManagerInterface $entityManager, BudgetProfileRepository $budgetProfileRepository, \App\Service\FinancialAnalysis\BudgetTrendCacheService $trendCacheService): Response
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
        $profileTrends = [];

        foreach ($budgetProfiles as $profile) {
            $profileTrends[$profile->getId()] = $trendCacheService->calculateTrends($profile);
        }

        return $this->render('financial-analysis/landing.html.twig', [
            'budgetProfiles' => $budgetProfiles,
            'profileTrends' => $profileTrends,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/profile/{id}/currency-rates', name: 'apps-financial-analysis-currency-rates', methods: ['GET'])]
    public function getCurrencyRates(
        BudgetProfile $budgetProfile,
        \App\Service\FinancialAnalysis\CurrencyExchangeService $currencyExchangeService
    ): Response {
        $rawJson = $currencyExchangeService->fetchRatesForProfile($budgetProfile->getId());
        $selectData = $currencyExchangeService->parseAndGroupRatesForSelect($rawJson);

        return $this->json([
            'results' => $selectData
        ]);
    }

    #[Route('/profile/{id}', name: 'apps-financial-analysis-profile')]
    public function overview(
        BudgetProfile $budgetProfile, 
        BudgetDashboardService $dashboardService, 
        Request $request, 
        EntityManagerInterface $entityManager, 
        ProjectBudgetRepository $projectBudgetRepository,
        \App\Service\FinancialAnalysis\BudgetTrendCacheService $trendCacheService,
        BudgetProfileRepository $budgetProfileRepository,
        \App\Repository\Projects\ProjectRepository $projectRepository,
        TransactionRepository $transactionRepository
    ): Response {
        $originalProfile = clone $budgetProfile;

        $form = $this->createForm(BudgetProfileType::class, $budgetProfile);
        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            $totals = ['allocated' => 0.0, 'expenses' => 0.0];
            if ($budgetProfile->getStartDate() && $budgetProfile->getEndDate()) {
                $totals = $projectBudgetRepository->getTotalsForFiscalYear($budgetProfile->getStartDate(), $budgetProfile->getEndDate());
                $budgetProfile->setTransientAllocatedBudgets($totals['allocated']);
                $budgetProfile->setTransientProjectExpenses($totals['expenses']);
            }
            
            // Snapshot state before updating the profile
            $trendCacheService->savePreUpdateState($budgetProfile, (float) $totals['allocated'], (float) $totals['expenses']);

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
            // Snapshot state because adding a budget changes allocated/remaining totals
            $totals = $budgetProfile->getStartDate() && $budgetProfile->getEndDate()
                ? $projectBudgetRepository->getTotalsForFiscalYear($budgetProfile->getStartDate(), $budgetProfile->getEndDate())
                : ['allocated' => 0.0, 'expenses' => 0.0];
            $trendCacheService->savePreUpdateState($budgetProfile, (float) $totals['allocated'], (float) $totals['expenses']);

            $projectBudgetRepository->save($projectBudget, true);
            $this->addFlash('success', 'Project Budget created successfully!');
            return $this->redirectToRoute('apps-financial-analysis-profile', ['id' => $budgetProfile->getId()]);
        }


        $projects = [];
        $uniqueProjectsCount = 0;
        $topProjectData = ['projectName' => 'N/A', 'budgetCount' => 0];
        $bestProjectData = ['name' => 'N/A', 'score' => 0];
        $projectsWithoutBudgetCount = 0;
        $totalTransactionsCount = 0;
        $progressionData = [];

        if ($originalProfile->getStartDate() && $originalProfile->getEndDate()) {
            $filteredBudgets = $projectBudgetRepository->findByFiscalYearScope($originalProfile->getStartDate(), $originalProfile->getEndDate());
            foreach ($filteredBudgets as $pb) {
                $projects[] = $dashboardService->formatBudgetDetails($pb);
            }

            $uniqueProjectsCount = $projectBudgetRepository->getUniqueProjectCountForFY($originalProfile->getStartDate(), $originalProfile->getEndDate());
            $topProjectData = $projectBudgetRepository->getProjectWithMostBudgetsForFY($originalProfile->getStartDate(), $originalProfile->getEndDate());

            // Calculate new KPIs via PHP object traversal
            $bestProjectData = ['name' => 'N/A', 'score' => -1000];
            $budgetedProjectIds = [];

            foreach ($filteredBudgets as $pb) {
                $totalTransactionsCount += $pb->getTransactions()->count();

                $project = $pb->getProject();
                if ($project) {
                    $budgetedProjectIds[] = $project->getId();

                    $total = (float) $pb->getTotalBudget();
                    $spent = (float) $pb->getActualSpend();
                    $utilization = $total > 0 ? ($spent / $total) * 100 : 0;
                    $progress = (float) $project->getProgress();

                    $score = $progress - $utilization;
                    if ($score > $bestProjectData['score']) {
                        $bestProjectData = ['name' => $project->getName(), 'score' => $score];
                    }

                    $progressionData[] = [
                        'projectName' => $project->getName(),
                        'progress' => $progress,
                        'total' => $total,
                        'spent' => $spent
                    ];
                }
            }

            if ($bestProjectData['score'] === -1000) {
                $bestProjectData['score'] = 0;
            }

            $allProjects = $projectRepository->findAll();
            foreach ($allProjects as $proj) {
                if (!in_array($proj->getId(), $budgetedProjectIds)) {
                    $projectsWithoutBudgetCount++;
                }
            }
        }

        $allProfiles = $budgetProfileRepository->findAll();
        $historicalData = [];
        foreach ($allProfiles as $prof) {
            $historicalData[] = [
                'year' => 'FY ' . $prof->getFiscalYear(),
                'allocated' => (float) $prof->getBudgetDisposable(),
                'spent' => (float) $prof->getTotalExpense(),
            ];
        }
        
        $budgetVal = (float) $originalProfile->getBudgetDisposable();
        $totals = $originalProfile->getStartDate() && $originalProfile->getEndDate() 
            ? $projectBudgetRepository->getTotalsForFiscalYear($originalProfile->getStartDate(), $originalProfile->getEndDate()) 
            : ['allocated' => 0.0, 'expenses' => 0.0];
        
        $remainingVal = $budgetVal - $totals['expenses'];
        $utilizationPercent = $budgetVal > 0 ? round(($totals['expenses'] / $budgetVal) * 100, 1) : 0;
        $cashFlowVal = $budgetVal - $totals['allocated'];

        // Calculate Trends for all 5 KPI Widgets
        $trends = $trendCacheService->calculateTrends($originalProfile, $totals['allocated'], $totals['expenses']);

        return $this->render('financial-analysis/overview.html.twig', [
            'budgetProfile' => $originalProfile,
            'projects' => array_slice($projects, 0, 6), // Show only top 6 on dashboard
            'form' => $form->createView(),
            'projectBudgetForm' => $projectBudgetForm->createView(),

            // Values
            'kpi_budget_value' => number_format($budgetVal / 1000, 1) . 'k',
            'kpi_spending_value' => number_format($totals['expenses'] / 1000, 1) . 'k',
            'kpi_remaining_value' => number_format($remainingVal / 1000, 1) . 'k',
            'kpi_utilization_value' => $utilizationPercent . '%',
            'kpi_cashflow_value' => number_format($cashFlowVal / 1000, 1) . 'k',

            // Badges & Trends (Managing the variables for overview.html.twig)
            'kpi_budget_badge' => $trends['budget']['badge'],
            'kpi_budget_class' => $trends['budget']['class'],
            'kpi_budget_icon' => 'ti ti-trending-' . $trends['budget']['direction'],

            'kpi_spending_badge' => $trends['spending']['badge'],
            'kpi_spending_class' => $trends['spending']['class'],
            'kpi_spending_icon' => 'ti ti-trending-' . $trends['spending']['direction'],

            'kpi_remaining_badge' => $trends['remaining']['badge'],
            'kpi_remaining_class' => $trends['remaining']['class'],
            'kpi_remaining_icon' => 'ti ti-trending-' . $trends['remaining']['direction'],

            'kpi_utilization_badge' => $trends['utilization']['badge'],
            'kpi_utilization_class' => $trends['utilization']['class'],
            'kpi_utilization_icon' => 'ti ti-trending-' . $trends['utilization']['direction'],

            'kpi_cashflow_badge' => $trends['cashflow']['badge'],
            'kpi_cashflow_class' => $trends['cashflow']['class'],
            'kpi_cashflow_icon' => 'ti ti-trending-' . $trends['cashflow']['direction'],

            // Progress Bars
            'kpi_budget_progress' => 100, // Budget limit is the 100% baseline
            'kpi_spending_progress' => $utilizationPercent,
            'kpi_remaining_progress' => 100 - $utilizationPercent,
            'kpi_utilization_progress' => $utilizationPercent,

            'currency_type' => $originalProfile->getBaseCurrency(),
            'uniqueProjectsCount' => $uniqueProjectsCount,
            'topProjectData' => $topProjectData,
            'bestProjectData' => $bestProjectData,
            'projectsWithoutBudgetCount' => $projectsWithoutBudgetCount,
            'totalTransactionsCount' => $totalTransactionsCount,
            'historicalData' => $historicalData,
            'progressionData' => $progressionData,
            'totalBudgetsCount' => count($projects),
        ]);

    }

    #[Route('/profile/{id}/projects', name: 'apps-financial-analysis-profile-projects')]
    public function allProjects(
        BudgetProfile $budgetProfile, 
        BudgetDashboardService $dashboardService, 
        Request $request, 
        ProjectBudgetRepository $projectBudgetRepository,
        \App\Service\FinancialAnalysis\BudgetTrendCacheService $trendCacheService
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
            // Snapshot profile because adding a budget affects the allocated aggregates
            $totals = $budgetProfile->getStartDate() && $budgetProfile->getEndDate()
                ? $projectBudgetRepository->getTotalsForFiscalYear($budgetProfile->getStartDate(), $budgetProfile->getEndDate())
                : ['allocated' => 0.0, 'expenses' => 0.0];
            $trendCacheService->savePreUpdateState($budgetProfile, (float) $totals['allocated'], (float) $totals['expenses']);

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
        ProjectBudgetRepository $projectBudgetRepository,
        TransactionRepository $transactionRepository,
        \App\Service\FinancialAnalysis\BudgetProjectStService $projectStService,
        \App\Service\FinancialAnalysis\BudgetTrendCacheService $trendCacheService
        ProjectBudgetRepository $projectBudgetRepository,
        \App\Service\AuthService $authService
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
            // Snapshot profile because changing budget affects the allocated share
            if ($profile) {
                $totals = $profile->getStartDate() && $profile->getEndDate()
                    ? $projectBudgetRepository->getTotalsForFiscalYear($profile->getStartDate(), $profile->getEndDate())
                    : ['allocated' => 0.0, 'expenses' => 0.0];
                $trendCacheService->savePreUpdateState($profile, (float) $totals['allocated'], (float) $totals['expenses']);
            }

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
            return $this->redirectToRoute('apps-financial-analysis-budget-details', ['id' => $projectBudget->getId(), '_fragment' => 'transactions-tab']);
        }

        $searchTerm = $request->query->get('q');
        $transactions = $dashboardService->formatTransactions($originalBudget, $searchTerm);

        if ($request->headers->get('X-Requested-With') === 'XMLHttpRequest') {
            return $this->render('financial-analysis/FA_components/_transaction_list_ajax.html.twig', [
                'transactions' => $transactions,
            ]);
        }

        // Temporary bypass: allow full access to the consultant interface
        $isConsultant = true;

        // Fetch Real Chart Data
        $monthlyData = $transactionRepository->getMonthlyAggregation($originalBudget->getId());
        $categoryData = $transactionRepository->getCategoryAggregation($originalBudget->getId());

        // Fetch Project Relation Stats (Use $projectBudget instead of cloned to avoid proxy loading issues)
        $budgetStats = $projectStService->calculateProjectBudgetStatistics($projectBudget);

        return $this->render('financial-analysis/budget_details.html.twig', [
            'projectBudget' => $dashboardService->formatBudgetDetails($originalBudget),
            'projectBudgetEntity' => $originalBudget,
            'budgetProfile' => $profile,
            'budgetProfile' => $profile,
            'projectBudgetForm' => $projectBudgetForm->createView(),
            'transactionForm' => $transactionForm->createView(),
            'transactions' => $transactions,
            'monthlyData' => $monthlyData,
            'categoryData' => $categoryData,
            'budgetStats' => $budgetStats,
        ]);
    }


    #[Route('/budget/{id}/analyze', name: 'apps-financial-analysis-analyze-budget', methods: ['POST'])]
    public function generateAnalysis(
        ProjectBudget $projectBudget,
        Request $request,
        \App\Service\FinancialAnalysis\OllamaAnalysisService $ollamaService
    ): Response {
        $payload = json_decode($request->getContent(), true);
        $userContext = $payload['userContext'] ?? null;

        $analysisJson = $ollamaService->analyzeProjectBudget($projectBudget, $userContext);

        return $this->json([
            'status' => 'success',
            'analysis' => $analysisJson
            'isConsultant' => $isConsultant,
        ]);
    }

    #[Route('/budget/{id}/consultant-drafts', name: 'apps-financial-analysis-consultant-drafts-list', methods: ['GET'])]
    public function ajaxConsultantDraftsList(
        ProjectBudget $projectBudget,
        Request $request,
        \App\Service\AuthService $authService,
        \App\Repository\FinancialAnalysis\ExpenseDraftRepository $expenseDraftRepository
    ): Response {
        $filter = $request->query->get('filter', 'flawed');

        $statuses = [];
        if ($filter === 'approved') {
            $statuses = ['APPROVED'];
        } elseif ($filter === 'pending') {
            $statuses = ['PASS'];
        } elseif ($filter === 'flawed') {
            $statuses = ['FLAGGED', 'PENDING'];
        } elseif ($filter === 'rejected') {
            $statuses = ['REJECTED'];
        }

        $qb = $expenseDraftRepository->createQueryBuilder('d')
            ->where('d.project_budget_related = :budget')
            ->setParameter('budget', $projectBudget)
            ->orderBy('d.createdAt', 'DESC');

        if (!empty($statuses)) {
            $qb->andWhere('d.status IN (:statuses)')
               ->setParameter('statuses', $statuses);
        }

        $drafts = $qb->getQuery()->getResult();

        return $this->render('financial-analysis/FA_components/_draft_list_ajax.html.twig', [
            'drafts' => $drafts,
            'active_filter' => $filter,
            'projectBudgetEntity' => $projectBudget
        ]);
    }

    #[Route('/consultant/draft/{id}/{action}', name: 'apps-financial-analysis-consultant-action', methods: ['POST'])]
    public function consultantAction(
        \App\Entity\FinancialAnalysis\ExpenseDraft $draft,
        string $action,
        Request $request,
        \App\Service\AuthService $authService,
        \App\Repository\FinancialAnalysis\ExpenseDraftRepository $expenseDraftRepository,
        BudgetDashboardService $dashboardService,
        \Symfony\Component\Validator\Validator\ValidatorInterface $validator,
        \App\Service\FinancialAnalysis\DraftNotificationService $notificationService
    ): Response {
        $reason = trim((string) $request->request->get('reason', ''));
        $pb = $draft->getProjectBudgetRelated();
        $pid = $pb ? $pb->getId() : 0;

        $creatorId = $draft->getCreatedBy() ? $draft->getCreatedBy()->getId() : null;

        if ($action === 'approve') {
            $expenseDraftRepository->approveDraft($draft);
            if ($creatorId) {
                $notificationService->addNotification($creatorId, 'Draft Approved', 'Your draft "' . $draft->getSubject() . '" has been approved by a consultant.', 'success');
            }
            $this->addFlash('success', 'Draft approved successfully.');
        } elseif ($action === 'revert') {
            $expenseDraftRepository->revertDraft($draft);
            if ($creatorId) {
                $notificationService->addNotification($creatorId, 'Draft Reverted', 'The decision on your draft "' . $draft->getSubject() . '" has been reverted to Flagged.', 'info');
            }
            $this->addFlash('info', 'Draft decision reverted to Flagged.');
        } elseif ($action === 'reject') {
            if (empty($reason)) {
                $evalData = $draft->getEvalData() ?? [];
                $generatedReasons = [];
                if (isset($evalData['tests']['budget_capacity']) && $evalData['tests']['budget_capacity']['status'] !== 'Pass') {
                    $generatedReasons[] = 'Budget Capacity: ' . ($evalData['tests']['budget_capacity']['message'] ?? 'Failed');
                }
                if (isset($evalData['tests']['statistical_anomaly']) && $evalData['tests']['statistical_anomaly']['status'] !== 'Pass') {
                    $z = $evalData['tests']['statistical_anomaly']['z_score'] ?? 'N/A';
                    $generatedReasons[] = 'Statistical Anomaly (Z-Score: ' . $z . ')';
                }
                if (isset($evalData['tests']['duplicate_check']) && $evalData['tests']['duplicate_check']['status'] !== 'Pass') {
                    $generatedReasons[] = 'Duplicate Check: ' . ($evalData['tests']['duplicate_check']['message'] ?? 'Failed');
                }
                $reason = !empty($generatedReasons) ? implode(' | ', $generatedReasons) : 'Rejected by consultant based on evaluation anomalies.';
            }

            $userId = (int) ($authService->getCurrentUserId() ?? 0);
            $expenseDraftRepository->rejectDraft($draft, $reason, $userId);

            if ($creatorId) {
                $notificationService->addNotification($creatorId, 'Draft Rejected', 'Your draft "' . $draft->getSubject() . '" was rejected: ' . $reason, 'error');
            }

            $this->addFlash('warning', 'Draft has been rejected.');
        } elseif ($action === 'to_transaction') {
            $transactionDateStr = $request->request->get('transaction_date');

            // Fallback: convert the draft's createdAt (which might be immutable) to a mutable \DateTime
            $transactionDate = $draft->getCreatedAt() ? \DateTime::createFromInterface($draft->getCreatedAt()) : new \DateTime();

            if ($transactionDateStr) {
                try {
                    $transactionDate = new \DateTime($transactionDateStr);
                } catch (\Exception $e) {
                    // Keep the fallback date if parsing fails
                }
            }

            $transaction = new Transaction();
            $transaction->setProjectBudget($pb);

            // Generate strict reference: TX- followed by 6 random digits
            $randomDigits = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
            $transaction->setReference('TX-' . $randomDigits);

            $transaction->setCost($draft->getAmount() ?: 0.0);
            $transaction->setDateStamp($transactionDate);
            $transaction->setExpenseCategory($draft->getCategory());
            $transaction->setDescription($draft->getDescription());

            // Apply strict validation constraints before persisting
            $errors = $validator->validate($transaction);
            if (count($errors) > 0) {
                $errorMessages = [];
                foreach ($errors as $error) {
                    $errorMessages[] = $error->getMessage();
                }
                $this->addFlash('danger', 'Validation failed: ' . implode(' ', $errorMessages));
                return $this->redirectToRoute('apps-financial-analysis-budget-details', ['id' => $pid, '_fragment' => 'transactions-tab']);
            }

            $profile = $dashboardService->getFiscalProfileForBudget($pb);
            $dashboardService->handleTransactionCascade($pb, $transaction, $profile);

            $expenseDraftRepository->remove($draft, true);

            if ($creatorId) {
                $notificationService->addNotification($creatorId, 'Draft Converted', 'Your draft "' . $draft->getSubject() . '" has been converted to a live transaction.', 'success');
            }

            $this->addFlash('success', 'Draft converted to a real transaction successfully!');
        }

        // Return to the main budget page
        return $this->redirectToRoute('apps-financial-analysis-budget-details', ['id' => $pid, '_fragment' => 'transactions-tab']);
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
            return $this->redirectToRoute('apps-financial-analysis-budget-details', ['id' => $projectBudget->getId(), '_fragment' => 'transactions-tab']);
        }

        // Native PHP/Twig approach: store errors in session flash bag to survive the redirect
        if ($form->isSubmitted() && !$form->isValid()) {
            $this->addFlash('danger', 'Failed to update transaction. Please check the errors in the form.');

            // Collect exact errors and put them in session
            $errors = [];
            foreach ($form->getErrors(true) as $error) {
                $errors[$error->getOrigin()->getName()] = $error->getMessage();
            }
            // Store specific errors for this specific transaction ID
            $request->getSession()->getFlashBag()->add('transaction_errors_' . $transaction->getId(), $errors);

            // Store the submitted invalid data so the form doesn't revert to old DB data
            $request->getSession()->getFlashBag()->add('transaction_data_' . $transaction->getId(), $request->request->all('transaction'));
        }

        return $this->redirectToRoute('apps-financial-analysis-budget-details', ['id' => $projectBudget->getId(), '_fragment' => 'transactions-tab']);
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

        return $this->redirectToRoute('apps-financial-analysis-budget-details', ['id' => $projectBudget->getId(), '_fragment' => 'transactions-tab']);
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
