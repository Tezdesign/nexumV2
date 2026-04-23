<?php

namespace App\Controller\Admin;

use App\Entity\UserHandling\Reclamation;
use App\Repository\UserHandling\ReclamationRepository;
use App\Service\ReclamationHistoryService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/admin/reclamations')]
class ReclamationHistoryController extends AbstractController
{
    public function __construct(
        private ReclamationRepository $reclamationRepository,
        private ReclamationHistoryService $historyService
    ) {}

    #[Route('/{id}/history', name: 'admin_reclamations_history', methods: ['GET'])]
    public function history(int $id): Response
    {
        $reclamation = $this->reclamationRepository->find($id);
        
        if (!$reclamation) {
            throw $this->createNotFoundException('Reclamation not found');
        }

        $history = $this->historyService->getReclamationHistory($id);

        return $this->render('admin/reclamation/history.html.twig', [
            'reclamation' => $reclamation,
            'history' => $history,
            'historyService' => $this->historyService,
        ]);
    }
}
