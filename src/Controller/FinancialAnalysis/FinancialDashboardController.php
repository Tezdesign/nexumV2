<?php

namespace App\Controller\FinancialAnalysis;

use App\Entity\FinancialAnalysis\BudgetProfile;
use App\Entity\FinancialAnalysis\ProjectBudget;
use App\Repository\FinancialAnalysis\BudgetProfileRepository;
use App\Service\FinancialAnalysis\BudgetDashboardService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/apps-financial-analysis')]
class FinancialDashboardController extends AbstractController
{
    #[Route('', name: 'apps-financial-analysis-landing')]
    public function index(BudgetProfileRepository $budgetProfileRepository): Response
    {
        $budgetProfiles = $budgetProfileRepository->findAll();

        return $this->render('financial-analysis/landing.html.twig', [
            'budgetProfiles' => $budgetProfiles
        ]);
    }

    #[Route('/profile/{id}', name: 'apps-financial-analysis-profile')]
    public function overview(BudgetProfile $budgetProfile, BudgetDashboardService $dashboardService): Response
    {
        // Future: Filter dashboardService projects based on budgetProfile dates
        return $this->render('financial-analysis/overview.html.twig', [
            'budgetProfile' => $budgetProfile,
            'projects' => $dashboardService->getFormattedBudgets()
        ]);
    }

    #[Route('/budget/{id}', name: 'apps-financial-analysis-budget-details')]
    public function budgetDetails(ProjectBudget $projectBudget): Response
    {
        $total = (float) $projectBudget->getTotalBudget();
        $spend = (float) $projectBudget->getActualSpend();
        $remaining = $total - $spend;
        $utilization = $total > 0 ? round(($spend / $total) * 100) : 0;

        $formattedBudget = [
            'id' => $projectBudget->getId(),
            'name' => $projectBudget->getName(),
            'projectName' => $projectBudget->getProject() ? $projectBudget->getProject()->getName() : 'Unknown Project',
            'totalBudget' => number_format($total / 1000, 1) . 'k',
            'actualSpend' => number_format($spend / 1000, 1) . 'k',
            'remaining' => number_format($remaining / 1000, 1) . 'k',
            'utilization' => $utilization,
        ];

        return $this->render('financial-analysis/budget_details.html.twig', [
            'projectBudget' => $formattedBudget,
        ]);
    }
}
