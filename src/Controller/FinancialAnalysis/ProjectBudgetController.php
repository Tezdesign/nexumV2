<?php

namespace App\Controller\FinancialAnalysis;

use App\Attribute\RequireAdmin;
use App\Attribute\RequireLogin;
use App\Entity\FinancialAnalysis\ProjectBudget;
use App\Form\FinancialAnalysis\ProjectBudgetType;
use App\Repository\FinancialAnalysis\ProjectBudgetRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/financial/analysis/project/budget')]
#[RequireLogin]
final class ProjectBudgetController extends AbstractController
{
    #[Route(name: 'app_financial_analysis_project_budget_index', methods: ['GET'])]
    public function index(ProjectBudgetRepository $projectBudgetRepository): Response
    {
        return $this->render('financial-analysis/project_budget/index.html.twig', [
            'project_budgets' => $projectBudgetRepository->findAll(),
        ]);
    }

    #[Route('/new', name: 'app_financial_analysis_project_budget_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $projectBudget = new ProjectBudget();
        $form = $this->createForm(ProjectBudgetType::class, $projectBudget);
        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            if ($form->isValid()) {
                $entityManager->persist($projectBudget);
                $entityManager->flush();
                $this->addFlash('success', 'Project budget created successfully.');

                return $this->redirectToRoute('app_financial_analysis_project_budget_index', [], Response::HTTP_SEE_OTHER);
            }

            foreach ($form->getErrors(true) as $error) {
                $this->addFlash('error', $error->getMessage());
            }
        }

        return $this->render('financial-analysis/project_budget/new.html.twig', [
            'project_budget' => $projectBudget,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_financial_analysis_project_budget_show', methods: ['GET'])]
    public function show(ProjectBudget $projectBudget): Response
    {
        return $this->render('financial-analysis/project_budget/show.html.twig', [
            'project_budget' => $projectBudget,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_financial_analysis_project_budget_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, ProjectBudget $projectBudget, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(ProjectBudgetType::class, $projectBudget);
        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            if ($form->isValid()) {
                $entityManager->flush();
                $this->addFlash('success', 'Project budget updated successfully.');

                return $this->redirectToRoute('app_financial_analysis_project_budget_index', [], Response::HTTP_SEE_OTHER);
            }

            foreach ($form->getErrors(true) as $error) {
                $this->addFlash('error', $error->getMessage());
            }
        }

        return $this->render('financial-analysis/project_budget/edit.html.twig', [
            'project_budget' => $projectBudget,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_financial_analysis_project_budget_delete', methods: ['POST'])]
    #[RequireAdmin]
    public function delete(Request $request, ProjectBudget $projectBudget, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$projectBudget->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($projectBudget);
            $entityManager->flush();
            $this->addFlash('success', 'Project budget deleted successfully.');
        } else {
            $this->addFlash('error', 'Invalid CSRF token.');
        }

        return $this->redirectToRoute('app_financial_analysis_project_budget_index', [], Response::HTTP_SEE_OTHER);
    }
}
