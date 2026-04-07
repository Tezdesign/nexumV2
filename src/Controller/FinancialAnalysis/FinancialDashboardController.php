<?php

namespace App\Controller\FinancialAnalysis;

use App\Entity\FinancialAnalysis\BudgetProfile;
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
}
