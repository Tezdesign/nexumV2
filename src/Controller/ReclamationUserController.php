<?php

namespace App\Controller;

use App\Attribute\RequireLogin;
use App\Controller\Trait\ReclamationAttachmentTrait;
use App\Controller\Trait\ValidationFlashTrait;
use App\Entity\UserHandling\Reclamation;
use App\Repository\UserHandling\ReclamationRepository;
use App\Service\AuthService;
use App\Service\ReclamationHistoryService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/mes-reclamations')]
#[RequireLogin]
class ReclamationUserController extends AbstractController
{
    use ReclamationAttachmentTrait;
    use ValidationFlashTrait;

    public function __construct(
        private readonly AuthService $authService,
        private readonly ReclamationRepository $reclamationRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly ValidatorInterface $validator,
        private readonly ReclamationHistoryService $historyService,
    ) {
    }


    private function checkCsrf(Request $request): void
    {
        if (!$this->isCsrfTokenValid('user_reclamations', (string) $request->request->get('_token'))) {
            throw new AccessDeniedHttpException('Invalid CSRF token.');
        }
    }

    #[Route('', name: 'mes_reclamations_index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $userId = $this->authService->getCurrentUserId();
        if ($userId === null) {
            return $this->redirectToRoute('welcome');
        }

        $sort = (string) $request->query->get('sort', 'date');
        $dir = strtoupper((string) $request->query->get('dir', 'DESC')) === 'ASC' ? 'ASC' : 'DESC';

        $reclamations = $this->reclamationRepository->findForUserListing($userId, $sort, $dir);
        $idsWithFile = array_flip($this->reclamationRepository->filterIdsHavingFichier(array_column($reclamations, 'idRec')));
        foreach ($reclamations as &$row) {
            $row['has_fichier'] = isset($idsWithFile[(int) $row['idRec']]);
        }
        unset($row);

        return $this->render('user/reclamation/index.html.twig', [
            'stats' => $this->reclamationRepository->getUserReclamationStats($userId),
            'reclamations' => $reclamations,
            'sort' => $sort,
            'dir' => $dir,
        ]);
    }

    #[Route('/nouvelle', name: 'mes_reclamations_create', methods: ['POST'])]
    public function create(Request $request): Response
    {
        $this->checkCsrf($request);
        $userId = $this->authService->getCurrentUserId();
        if ($userId === null) {
            return $this->redirectToRoute('welcome');
        }

        $rec = new Reclamation();
        $rec->setTitre(trim((string) $request->request->get('titre', '')));
        $cat = trim((string) $request->request->get('categorie', ''));
        $rec->setCategorie($cat !== '' ? $cat : null);
        $proj = trim((string) $request->request->get('projet', ''));
        $rec->setProjet($proj !== '' ? $proj : null);

        if ($this->flashValidationErrors($this->validator->validate($rec, null, ['reclamation_user']))) {
            return $this->redirectToRoute('mes_reclamations_index');
        }

        $rec->setStatut('pending');
        $rec->setIdUser($userId);
        $rec->setDate((new \DateTimeImmutable())->format('Y-m-d H:i'));

        $this->entityManager->persist($rec);
        $this->entityManager->flush();

        // Log reclamation creation
        $this->historyService->logActivity((int) $rec->getIdRec(), 'create', [
            'titre' => $rec->getTitre(),
            'categorie' => $rec->getCategorie(),
            'projet' => $rec->getProjet(),
            'statut' => $rec->getStatut()
        ]);

        $binary = $this->uploadedAttachment($request);
        if ($binary !== null) {
            $this->storeAttachment($rec, $binary);
        }

        $this->addFlash('success', 'Your reclamation was submitted successfully.');

        return $this->redirectToRoute('mes_reclamations_index');
    }

    #[Route('/{id}/fichier', name: 'mes_reclamations_fichier', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function fichier(int $id): Response
    {
        $userId = $this->authService->getCurrentUserId();
        if ($userId === null) {
            return $this->redirectToRoute('welcome');
        }

        $rec = $this->reclamationRepository->find($id);
        if (!$rec instanceof Reclamation || $rec->getIdUser() !== $userId) {
            throw $this->createNotFoundException();
        }

        return $this->attachmentResponse($rec);
    }

    #[Route('/{id}/supprimer', name: 'mes_reclamations_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function delete(Request $request, int $id): Response
    {
        $this->checkCsrf($request);
        $userId = $this->authService->getCurrentUserId();
        if ($userId === null) {
            return $this->redirectToRoute('welcome');
        }

        $rec = $this->reclamationRepository->find($id);
        if (!$rec instanceof Reclamation || $rec->getIdUser() !== $userId) {
            throw $this->createNotFoundException('Reclamation not found.');
        }

        // Log reclamation deletion before removing
        $this->historyService->logActivity((int) $rec->getIdRec(), 'delete', [
            'titre' => $rec->getTitre(),
            'categorie' => $rec->getCategorie(),
            'statut' => $rec->getStatut()
        ]);

        $this->entityManager->remove($rec);
        $this->entityManager->flush();
        $this->addFlash('success', 'Reclamation removed.');

        return $this->redirectToRoute('mes_reclamations_index');
    }

    #[Route('/{id}/history', name: 'mes_reclamations_history', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function history(int $id): Response
    {
        $userId = $this->authService->getCurrentUserId();
        if ($userId === null) {
            return $this->redirectToRoute('welcome');
        }

        $rec = $this->reclamationRepository->find($id);
        if (!$rec instanceof Reclamation || $rec->getIdUser() !== $userId) {
            throw $this->createNotFoundException('Reclamation not found.');
        }

        $history = $this->historyService->getReclamationHistory($id);

        return $this->render('user/reclamation/history.html.twig', [
            'reclamation' => $rec,
            'history' => $history,
            'historyService' => $this->historyService,
        ]);
    }
}
