<?php

namespace App\Controller\Admin;

use App\Controller\Trait\ValidationFlashTrait;
use App\Entity\UserHandling\Reclamation;
use App\Repository\UserHandling\ReclamationRepository;
use App\Repository\UserHandling\UtilisateurRepository;
use App\Service\AdminPdfExportService;
use App\Service\AuthService;
use App\Service\MailService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/admin/reclamations')]
class ReclamationManagementController extends AbstractController
{
    use ValidationFlashTrait;

    public function __construct(
        private readonly AuthService $authService,
        private readonly ReclamationRepository $reclamationRepository,
        private readonly UtilisateurRepository $utilisateurRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly ValidatorInterface $validator,
        private readonly MailService $mailService,
    ) {
    }

    private function ensureAdmin(): ?Response
    {
        if (!$this->authService->isLoggedIn()) {
            return $this->redirectToRoute('welcome');
        }
        if (!$this->authService->isAdmin()) {
            $this->addFlash('error', 'You do not have access to the administration area.');

            return $this->redirectToRoute('dashboard');
        }

        return null;
    }

    private function reclamationUsersList(): array
    {
        return $this->utilisateurRepository->findBy([], ['nom' => 'ASC', 'prenom' => 'ASC']);
    }

    private function isModalSubmit(Request $request): bool
    {
        return $request->isMethod('POST') && $request->request->getBoolean('_modal');
    }

    private function wantsFormFragment(Request $request): bool
    {
        return $request->query->getBoolean('fragment');
    }

    private function reclamationFormPartialResponse(string $mode, ?Reclamation $reclamation, int $status = Response::HTTP_OK): Response
    {
        return new Response(
            $this->renderView('admin/reclamation/_form_inner.html.twig', [
                'mode' => $mode,
                'reclamation' => $reclamation,
                'users' => $this->reclamationUsersList(),
                'modal' => true,
            ]),
            $status
        );
    }

    #[Route('', name: 'admin_reclamations_index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        if ($r = $this->ensureAdmin()) {
            return $r;
        }

        $sort = (string) $request->query->get('sort', 'date');
        $dir = strtoupper((string) $request->query->get('dir', 'DESC')) === 'ASC' ? 'ASC' : 'DESC';

        $chartStatuts = $this->reclamationRepository->getChartDataByStatut();
        $chartCategories = $this->reclamationRepository->getChartDataByCategorie();

        $reclamations = $this->reclamationRepository->findForAdminListing($sort, $dir);
        $idsWithFile = array_flip($this->reclamationRepository->filterIdsHavingFichier(array_column($reclamations, 'idRec')));
        foreach ($reclamations as &$row) {
            $row['has_fichier'] = isset($idsWithFile[(int) $row['idRec']]);
        }
        unset($row);

        return $this->render('admin/reclamation/index.html.twig', [
            'stats' => $this->reclamationRepository->getAdminReclamationStats(),
            'charts_json' => json_encode(
                ['statuts' => $chartStatuts, 'categories' => $chartCategories],
                \JSON_HEX_TAG | \JSON_HEX_APOS | \JSON_HEX_AMP | \JSON_HEX_QUOT
            ),
            'reclamations' => $reclamations,
            'sort' => $sort,
            'dir' => $dir,
        ]);
    }

    #[Route('/export.pdf', name: 'admin_reclamations_export_pdf', methods: ['GET'])]
    public function exportPdf(Request $request, AdminPdfExportService $pdfExport): Response
    {
        if ($r = $this->ensureAdmin()) {
            return $r;
        }

        $sort = (string) $request->query->get('sort', 'date');
        $dir = strtoupper((string) $request->query->get('dir', 'DESC')) === 'ASC' ? 'ASC' : 'DESC';

        $reclamations = $this->reclamationRepository->findForAdminListing($sort, $dir);
        $idsWithFile = array_flip($this->reclamationRepository->filterIdsHavingFichier(array_column($reclamations, 'idRec')));
        foreach ($reclamations as &$row) {
            $row['has_fichier'] = isset($idsWithFile[(int) $row['idRec']]);
        }
        unset($row);

        return $pdfExport->renderTablePdf(
            'admin/export/reclamations_pdf.html.twig',
            [
                'reclamations' => $reclamations,
                'stats' => $this->reclamationRepository->getAdminReclamationStats(),
                'generated_at' => new \DateTimeImmutable(),
                'sort' => $sort,
                'dir' => $dir,
            ],
            'nexum-reclamations-' . (new \DateTimeImmutable())->format('Y-m-d') . '.pdf',
        );
    }

    #[Route('/new', name: 'admin_reclamations_new', methods: ['GET', 'POST'])]
    public function newReclamation(Request $request): Response
    {
        if ($r = $this->ensureAdmin()) {
            return $r;
        }

        if ($request->isMethod('POST')) {
            $modal = $this->isModalSubmit($request);
            $rec = new Reclamation();
            $this->fillReclamationFromRequest($rec, $request, true);
            if ($this->flashValidationErrors($this->validator->validate($rec, null, ['reclamation_admin']))) {
                return $modal
                    ? $this->reclamationFormPartialResponse('create', null, Response::HTTP_UNPROCESSABLE_ENTITY)
                    : $this->render('admin/reclamation/form.html.twig', [
                        'mode' => 'create',
                        'reclamation' => null,
                        'users' => $this->reclamationUsersList(),
                    ]);
            }

            $idUser = (int) $rec->getIdUser();
            if (!$this->utilisateurRepository->find($idUser)) {
                $this->addFlash('error', 'Selected user does not exist.');

                return $modal
                    ? $this->reclamationFormPartialResponse('create', null, Response::HTTP_UNPROCESSABLE_ENTITY)
                    : $this->render('admin/reclamation/form.html.twig', [
                        'mode' => 'create',
                        'reclamation' => null,
                        'users' => $this->reclamationUsersList(),
                    ]);
            }

            $this->entityManager->persist($rec);
            $this->entityManager->flush();
            $this->attachUploadedFileIfAny($rec, $request, true);

            $this->addFlash('success', 'Reclamation created.');

            return $modal
                ? new JsonResponse(['ok' => true, 'redirect' => $this->generateUrl('admin_reclamations_index')])
                : $this->redirectToRoute('admin_reclamations_index');
        }

        if ($this->wantsFormFragment($request)) {
            return $this->reclamationFormPartialResponse('create', null);
        }

        return $this->render('admin/reclamation/form.html.twig', [
            'mode' => 'create',
            'reclamation' => null,
            'users' => $this->reclamationUsersList(),
        ]);
    }

    #[Route('/{id}/edit', name: 'admin_reclamations_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function edit(Request $request, int $id): Response
    {
        if ($r = $this->ensureAdmin()) {
            return $r;
        }

        $rec = $this->reclamationRepository->find($id);
        if (!$rec instanceof Reclamation) {
            throw $this->createNotFoundException('Reclamation not found.');
        }

        if ($request->isMethod('POST')) {
            $modal = $this->isModalSubmit($request);
            $oldStatus = strtolower(trim((string) $rec->getStatut()));
            $this->fillReclamationFromRequest($rec, $request, false);
            if ($this->flashValidationErrors($this->validator->validate($rec, null, ['reclamation_admin']))) {
                return $modal
                    ? $this->reclamationFormPartialResponse('edit', $rec, Response::HTTP_UNPROCESSABLE_ENTITY)
                    : $this->render('admin/reclamation/form.html.twig', [
                        'mode' => 'edit',
                        'reclamation' => $rec,
                        'users' => $this->reclamationUsersList(),
                    ]);
            }

            $idUser = (int) $rec->getIdUser();
            if (!$this->utilisateurRepository->find($idUser)) {
                $this->addFlash('error', 'Selected user does not exist.');

                return $modal
                    ? $this->reclamationFormPartialResponse('edit', $rec, Response::HTTP_UNPROCESSABLE_ENTITY)
                    : $this->render('admin/reclamation/form.html.twig', [
                        'mode' => 'edit',
                        'reclamation' => $rec,
                        'users' => $this->reclamationUsersList(),
                    ]);
            }

            $this->entityManager->flush();
            $this->attachUploadedFileIfAny($rec, $request, true);
            $newStatus = strtolower(trim((string) $rec->getStatut()));

            if ($oldStatus !== $newStatus) {
                $owner = $this->utilisateurRepository->find((int) $rec->getIdUser());
                $ownerEmail = trim((string) ($owner?->getEmail() ?? ''));
                if ($ownerEmail !== '') {
                    try {
                        $this->mailService->sendReclamationStatusEmail(
                            $ownerEmail,
                            (string) ($rec->getTitre() ?? ('Reclamation #' . (string) $rec->getIdRec())),
                            $rec->getProjet(),
                            $newStatus
                        );
                    } catch (\Throwable) {
                        $this->addFlash('warning', 'Reclamation updated, but status email could not be sent.');
                    }
                }
            }

            $this->addFlash('success', 'Reclamation updated.');

            return $modal
                ? new JsonResponse(['ok' => true, 'redirect' => $this->generateUrl('admin_reclamations_index')])
                : $this->redirectToRoute('admin_reclamations_index');
        }

        if ($this->wantsFormFragment($request)) {
            return $this->reclamationFormPartialResponse('edit', $rec);
        }

        return $this->render('admin/reclamation/form.html.twig', [
            'mode' => 'edit',
            'reclamation' => $rec,
            'users' => $this->reclamationUsersList(),
        ]);
    }

    #[Route('/{id}/fichier', name: 'admin_reclamations_fichier', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function fichier(int $id): Response
    {
        if ($r = $this->ensureAdmin()) {
            return $r;
        }

        $rec = $this->reclamationRepository->find($id);
        if (!$rec instanceof Reclamation) {
            throw $this->createNotFoundException();
        }

        return $this->attachmentResponse($rec);
    }

    #[Route('/{id}/delete', name: 'admin_reclamations_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function delete(int $id): Response
    {
        if ($r = $this->ensureAdmin()) {
            return $r;
        }

        $rec = $this->reclamationRepository->find($id);
        if (!$rec instanceof Reclamation) {
            throw $this->createNotFoundException('Reclamation not found.');
        }

        $this->entityManager->remove($rec);
        $this->entityManager->flush();
        $this->addFlash('success', 'Reclamation deleted.');

        return $this->redirectToRoute('admin_reclamations_index');
    }

    private function fillReclamationFromRequest(Reclamation $rec, Request $request, bool $isNew): void
    {
        $rec->setTitre(trim((string) $request->request->get('titre', '')));
        $cat = trim((string) $request->request->get('categorie', ''));
        $rec->setCategorie($cat !== '' ? $cat : null);
        $proj = trim((string) $request->request->get('projet', ''));
        $rec->setProjet($proj !== '' ? $proj : null);
        $rec->setStatut(strtolower(trim((string) $request->request->get('statut', 'pending'))));

        $idUser = (int) $request->request->get('id_user', 0);
        if ($idUser > 0) {
            $rec->setIdUser($idUser);
        }

        if ($isNew) {
            $rec->setDate((new \DateTimeImmutable())->format('Y-m-d H:i'));
        }
    }

    private function attachUploadedFileIfAny(Reclamation $rec, Request $request, bool $isExistingEntity): void
    {
        $file = $request->files->get('fichier');
        if (!$file instanceof UploadedFile || $file->getError() !== UPLOAD_ERR_OK) {
            return;
        }

        $path = $file->getRealPath() ?: $file->getPathname();
        $binary = @file_get_contents($path);
        if ($binary === false || $binary === '') {
            $this->addFlash('warning', 'Attachment could not be read.');

            return;
        }

        if ($isExistingEntity && $rec->getIdRec() !== null) {
            $this->entityManager->getConnection()->executeStatement(
                'UPDATE reclamation SET fichier = ? WHERE idRec = ?',
                [$binary, $rec->getIdRec()]
            );
        }

        $rec->setFichier($binary);
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
