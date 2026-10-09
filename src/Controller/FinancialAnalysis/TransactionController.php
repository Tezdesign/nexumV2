<?php

namespace App\Controller\FinancialAnalysis;

use App\Attribute\RequireAdmin;
use App\Attribute\RequireLogin;
use App\Entity\FinancialAnalysis\Transaction;
use App\Form\FinancialAnalysis\TransactionType;
use App\Repository\FinancialAnalysis\TransactionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/financial/analysis/transaction')]
#[RequireLogin]
final class TransactionController extends AbstractController
{
    #[Route(name: 'app_financial_analysis_transaction_index', methods: ['GET'])]
    public function index(TransactionRepository $transactionRepository): Response
    {
        return $this->render('financial-analysis/transaction/index.html.twig', [
            'transactions' => $transactionRepository->findAll(),
        ]);
    }

    #[Route('/new', name: 'app_financial_analysis_transaction_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $transaction = new Transaction();
        $form = $this->createForm(TransactionType::class, $transaction);
        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            if ($form->isValid()) {
                $entityManager->persist($transaction);
                $entityManager->flush();
                $this->addFlash('success', 'Transaction created successfully.');

                return $this->redirectToRoute('app_financial_analysis_transaction_index', [], Response::HTTP_SEE_OTHER);
            }

            foreach ($form->getErrors(true) as $error) {
                $this->addFlash('error', $error->getMessage());
            }
        }

        return $this->render('financial-analysis/transaction/new.html.twig', [
            'transaction' => $transaction,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_financial_analysis_transaction_show', methods: ['GET'])]
    public function show(Transaction $transaction): Response
    {
        return $this->render('financial-analysis/transaction/show.html.twig', [
            'transaction' => $transaction,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_financial_analysis_transaction_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Transaction $transaction, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(TransactionType::class, $transaction);
        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            if ($form->isValid()) {
                $entityManager->flush();
                $this->addFlash('success', 'Transaction updated successfully.');

                return $this->redirectToRoute('app_financial_analysis_transaction_index', [], Response::HTTP_SEE_OTHER);
            }

            foreach ($form->getErrors(true) as $error) {
                $this->addFlash('error', $error->getMessage());
            }
        }

        return $this->render('financial-analysis/transaction/edit.html.twig', [
            'transaction' => $transaction,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_financial_analysis_transaction_delete', methods: ['POST'])]
    #[RequireAdmin]
    public function delete(Request $request, Transaction $transaction, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$transaction->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($transaction);
            $entityManager->flush();
            $this->addFlash('success', 'Transaction deleted successfully.');
        } else {
            $this->addFlash('error', 'Invalid CSRF token.');
        }

        return $this->redirectToRoute('app_financial_analysis_transaction_index', [], Response::HTTP_SEE_OTHER);
    }
}
