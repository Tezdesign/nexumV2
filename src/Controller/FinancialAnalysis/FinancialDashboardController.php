<?php

namespace App\Controller\FinancialAnalysis;

use App\Entity\FinancialAnalysis\BudgetProfile;
use App\Entity\FinancialAnalysis\ProjectBudget;
use App\Form\FinancialAnalysis\BudgetProfileType;
use App\Form\FinancialAnalysis\ProjectBudgetType;
use App\Repository\FinancialAnalysis\BudgetProfileRepository;
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
    public function overview(BudgetProfile $budgetProfile, BudgetDashboardService $dashboardService, Request $request, EntityManagerInterface $entityManager, \App\Repository\FinancialAnalysis\ProjectBudgetRepository $projectBudgetRepository): Response
    {
        // Clone the original profile from the database before the form mutates it!
        // This ensures if the user submits an invalid update, the background FY dashboard
        // doesn't temporarily show their broken values.
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

        // Fetch projects falling within the scope of this Fiscal Year (using original dates)
        $projects = [];
        if ($originalProfile->getStartDate() && $originalProfile->getEndDate()) {
            $filteredBudgets = $projectBudgetRepository->findByFiscalYearScope($originalProfile->getStartDate(), $originalProfile->getEndDate());
            
            // Format the budgets for the view
            foreach ($filteredBudgets as $pb) {
                $total = (float) $pb->getTotalBudget();
                $spend = (float) $pb->getActualSpend();
                $remaining = $total - $spend;
                $utilization = $total > 0 ? round(($spend / $total) * 100) : 0;
                $dueDate = $pb->getDueDate() ? $pb->getDueDate()->format('M d, Y') : 'N/A';
                $status = $pb->getStatus();

                $projects[] = [
                    'id' => $pb->getId(),
                    'name' => $pb->getName(),
                    'projectName' => $pb->getProject() ? $pb->getProject()->getName() : 'Unknown Project',
                    'totalBudget' => number_format($total / 1000, 1) . 'k',
                    'actualSpend' => number_format($spend / 1000, 1) . 'k',
                    'remaining' => number_format($remaining / 1000, 1) . 'k',
                    'dueDate' => $dueDate,
                    'utilization' => $utilization,
                    'status' => $status,
                ];
            }
        }
        
        // Let's set up the live KPI data using the original database values
        $budgetVal = (float) $originalProfile->getBudgetDisposable();
        $allocatedVal = $originalProfile->getStartDate() && $originalProfile->getEndDate() ? $projectBudgetRepository->getTotalsForFiscalYear($originalProfile->getStartDate(), $originalProfile->getEndDate())['allocated'] : 0.0;
        $expensesVal = $originalProfile->getStartDate() && $originalProfile->getEndDate() ? $projectBudgetRepository->getTotalsForFiscalYear($originalProfile->getStartDate(), $originalProfile->getEndDate())['expenses'] : 0.0;
        
        $remainingVal = $budgetVal - $expensesVal;
        $utilizationPercent = $budgetVal > 0 ? round(($expensesVal / $budgetVal) * 100, 1) : 0;

        return $this->render('financial-analysis/overview.html.twig', [
            'budgetProfile' => $originalProfile, // Pass the original profile so breadcrumbs don't change
            'projects' => $projects,
            'form' => $form->createView(), // The form uses the mutated object, preserving the user's invalid input
            'projectBudgetForm' => $projectBudgetForm->createView(),
            
            // Live KPIs
            'kpi_budget_value' => number_format($budgetVal / 1000, 1) . 'k',
            'kpi_spending_value' => number_format($expensesVal / 1000, 1) . 'k',
            'kpi_remaining_value' => number_format($remainingVal / 1000, 1) . 'k',
            'kpi_utilization_value' => $utilizationPercent . '%',
            'kpi_cashflow_value' => number_format(($budgetVal - $allocatedVal) / 1000, 1) . 'k',
        ]);
    }

    #[Route('/budget/{id}', name: 'apps-financial-analysis-budget-details')]
    public function budgetDetails(ProjectBudget $projectBudget): Response
    {
        $total = (float) $projectBudget->getTotalBudget();
        $spend = (float) $projectBudget->getActualSpend();
        $remaining = $total - $spend;
        $utilization = $total > 0 ? round(($spend / $total) * 100) : 0;
        $dueDate = $projectBudget->getDueDate()->format('M d, Y');
        $status = $projectBudget->getStatus();

        $formattedBudget = [
            'id' => $projectBudget->getId(),
            'name' => $projectBudget->getName(),
            'projectName' => $projectBudget->getProject() ? $projectBudget->getProject()->getName() : 'Unknown Project',
            'totalBudget' => number_format($total / 1000, 1) . 'k',
            'actualSpend' => number_format($spend / 1000, 1) . 'k',
            'remaining' => number_format($remaining / 1000, 1) . 'k',
            'utilization' => $utilization,
            'dueDate' => $dueDate,
            'status' => $status,
        ];

        return $this->render('financial-analysis/budget_details.html.twig', [
            'projectBudget' => $formattedBudget,
        ]);
    }

    //boilerplate code here not useful anymore

//    #[Route('/test-form', name: 'apps-financial-analysis-test-form')]
//    public function testForm(Request $request, EntityManagerInterface $entityManager): Response
//    {
//        $budgetProfile = new BudgetProfile();
//        $form = $this->createForm(BudgetProfileType::class, $budgetProfile);
//        $form->handleRequest($request);
//
//        if ($form->isSubmitted() && $form->isValid()) {
//            $this->addFlash('success', 'Form is valid! But we wont save in this test.');
//            return $this->redirectToRoute('apps-financial-analysis-test-form');
//        }
//
//        return $this->render('financial-analysis/test_form.html.twig', [
//            'form' => $form->createView(),
//        ]);
//    }
}
