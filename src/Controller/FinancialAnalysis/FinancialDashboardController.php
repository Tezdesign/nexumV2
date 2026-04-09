<?php

namespace App\Controller\FinancialAnalysis;

use App\Entity\FinancialAnalysis\BudgetProfile;
use App\Entity\FinancialAnalysis\ProjectBudget;
use App\Form\FinancialAnalysis\BudgetProfileType;
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
    public function overview(BudgetProfile $budgetProfile, BudgetDashboardService $dashboardService, Request $request, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(BudgetProfileType::class, $budgetProfile);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();
            $this->addFlash('success', 'Budget Profile updated successfully!');
            return $this->redirectToRoute('apps-financial-analysis-profile', ['id' => $budgetProfile->getId()]);
        }

        // Future: Filter dashboardService projects based on budgetProfile dates
        return $this->render('financial-analysis/overview.html.twig', [
            'budgetProfile' => $budgetProfile,
            'projects' => $dashboardService->getFormattedBudgets(),
            'form' => $form->createView(),
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

    #[Route('/test-form', name: 'apps-financial-analysis-test-form')]
    public function testForm(Request $request, EntityManagerInterface $entityManager): Response
    {
        $budgetProfile = new BudgetProfile();
        $form = $this->createForm(BudgetProfileType::class, $budgetProfile);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->addFlash('success', 'Form is valid! But we wont save in this test.');
            return $this->redirectToRoute('apps-financial-analysis-test-form');
        }

        return $this->render('financial-analysis/test_form.html.twig', [
            'form' => $form->createView(),
        ]);
    }
}
