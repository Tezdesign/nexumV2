<?php

namespace App\Controller;

use App\Controller\Trait\ValidationFlashTrait;
use App\Entity\UserHandling\Reclamation;
use App\Repository\UserHandling\ReclamationRepository;
use App\Service\AuthService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/mes-reclamations')]
class ReclamationUserController extends AbstractController
{
    use ValidationFlashTrait;

    public function __construct(
        private readonly AuthService $authService,
        private readonly ReclamationRepository $reclamationRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly ValidatorInterface $validator,
    ) {
    }

    private function ensureUser(): ?Response
    {
        if (!$this->authService->isLoggedIn()) {
            return $this->redirectToRoute('welcome');
        }

        return null;
    }

    #[Route('', name: 'mes_reclamations_index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        if ($r = $this->ensureUser()) {
            return $r;
        }

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
        if ($r = $this->ensureUser()) {
            return $r;
        }

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

        $file = $request->files->get('fichier');
        if ($file instanceof UploadedFile && $file->getError() === UPLOAD_ERR_OK) {
            $path = $file->getRealPath() ?: $file->getPathname();
            $binary = @file_get_contents($path);
            if ($binary !== false && $binary !== '') {
                $this->entityManager->getConnection()->executeStatement(
                    'UPDATE reclamation SET fichier = ? WHERE idRec = ?',
                    [$binary, $rec->getIdRec()]
                );
                $rec->setFichier($binary);
            }
        }

        $this->addFlash('success', 'Your reclamation was submitted successfully.');

        return $this->redirectToRoute('mes_reclamations_index');
    }

    #[Route('/{id}/fichier', name: 'mes_reclamations_fichier', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function fichier(int $id): Response
    {
        if ($r = $this->ensureUser()) {
            return $r;
        }

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
    public function delete(int $id): Response
    {
        if ($r = $this->ensureUser()) {
            return $r;
        }

        $userId = $this->authService->getCurrentUserId();
        if ($userId === null) {
            return $this->redirectToRoute('welcome');
        }

        $rec = $this->reclamationRepository->find($id);
        if (!$rec instanceof Reclamation || $rec->getIdUser() !== $userId) {
            throw $this->createNotFoundException('Reclamation not found.');
        }

        $this->entityManager->remove($rec);
        $this->entityManager->flush();
        $this->addFlash('success', 'Reclamation removed.');

        return $this->redirectToRoute('mes_reclamations_index');
    }

    private function attachmentResponse(Reclamation $rec): Response
    {
        $data = $rec->getFichier();
        if (\is_resource($data)) {
            $data = stream_get_contents($data) ?: '';
        }
        if (!\is_string($data) || $data === '') {
            throw $this->createNotFoundException('No attachment.');
        }

        $mime = 'application/octet-stream';
        if (\class_exists(\finfo::class)) {
            $finfo = new \finfo(FILEINFO_MIME_TYPE);
            $detected = $finfo->buffer($data);
            if (\is_string($detected) && $detected !== '') {
                $mime = $detected;
            }
        }

        return new Response($data, Response::HTTP_OK, [
            'Content-Type' => $mime,
            'Content-Disposition' => 'inline; filename="reclamation-' . (int) $rec->getIdRec() . '"',
            'Cache-Control' => 'private, max-age=0, must-revalidate',
        ]);
    }
}
