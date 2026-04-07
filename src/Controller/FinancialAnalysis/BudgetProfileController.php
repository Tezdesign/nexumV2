<?php

namespace App\Controller\FinancialAnalysis;

use App\Entity\FinancialAnalysis\BudgetProfile;
use App\Form\FinancialAnalysis\BudgetProfileType;
use App\Repository\FinancialAnalysis\BudgetProfileRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/financial/analysis/budget/profile')]
final class BudgetProfileController extends AbstractController
{
    #[Route(name: 'app_financial_analysis_budget_profile_index', methods: ['GET'])]
    public function index(BudgetProfileRepository $budgetProfileRepository): Response
    {
        return $this->render('financial-analysis/budget_profile/index.html.twig', [
            'budget_profiles' => $budgetProfileRepository->findAll(),
        ]);
    }

    #[Route('/new', name: 'app_financial_analysis_budget_profile_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $budgetProfile = new BudgetProfile();
        $form = $this->createForm(BudgetProfileType::class, $budgetProfile);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($budgetProfile);
            $entityManager->flush();

            return $this->redirectToRoute('app_financial_analysis_budget_profile_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('financial-analysis/budget_profile/new.html.twig', [
            'budget_profile' => $budgetProfile,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_financial_analysis_budget_profile_show', methods: ['GET'])]
    public function show(BudgetProfile $budgetProfile): Response
    {
        return $this->render('financial-analysis/budget_profile/show.html.twig', [
            'budget_profile' => $budgetProfile,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_financial_analysis_budget_profile_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, BudgetProfile $budgetProfile, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(BudgetProfileType::class, $budgetProfile);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            return $this->redirectToRoute('app_financial_analysis_budget_profile_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('financial-analysis/budget_profile/edit.html.twig', [
            'budget_profile' => $budgetProfile,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_financial_analysis_budget_profile_delete', methods: ['POST'])]
    public function delete(Request $request, BudgetProfile $budgetProfile, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$budgetProfile->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($budgetProfile);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_financial_analysis_budget_profile_index', [], Response::HTTP_SEE_OTHER);
    }
}
