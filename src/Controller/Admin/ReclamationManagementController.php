<?php

namespace App\Controller\Admin;

use App\Controller\Trait\ReclamationAttachmentTrait;
use App\Controller\Trait\ValidationFlashTrait;
use App\Entity\UserHandling\Reclamation;
use App\Repository\UserHandling\ReclamationRepository;
use App\Repository\UserHandling\UtilisateurRepository;
use App\Entity\UserHandling\Utilisateur;
use App\Service\AdminPdfExportService;
use App\Service\ReclamationHistoryService;
use Doctrine\ORM\EntityManagerInterface;
use App\Attribute\RequireAdmin;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/admin/reclamations')]
#[RequireAdmin]
class ReclamationManagementController extends AbstractController
{
    use ReclamationAttachmentTrait;
    use ValidationFlashTrait;

    public function __construct(
        private readonly ReclamationRepository $reclamationRepository,
        private readonly UtilisateurRepository $utilisateurRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly ValidatorInterface $validator,
        private readonly ReclamationHistoryService $historyService,
    ) {
    }

    /**
     * @return Utilisateur[]
     */
    private function reclamationUsersList(): array
    {
        return $this->utilisateurRepository->findBy([], ['nom' => 'ASC', 'prenom' => 'ASC']);
    }

    private function checkCsrf(Request $request): void
    {
        if (!$this->isCsrfTokenValid('admin_reclamations', (string) $request->request->get('_token'))) {
            throw new AccessDeniedHttpException('Invalid CSRF token.');
        }
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
        if ($request->isMethod('POST')) {
            $this->checkCsrf($request);
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
            $this->attachUploadedFileIfAny($rec, $request);

            // Log reclamation creation
            $this->historyService->logActivity((int) $rec->getIdRec(), 'create', [
                'titre' => $rec->getTitre(),
                'categorie' => $rec->getCategorie(),
                'projet' => $rec->getProjet(),
                'statut' => $rec->getStatut(),
                'assigned_user' => $rec->getIdUser()
            ]);

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
        $rec = $this->reclamationRepository->find($id);
        if (!$rec instanceof Reclamation) {
            throw $this->createNotFoundException('Reclamation not found.');
        }

        if ($request->isMethod('POST')) {
            $this->checkCsrf($request);
            $modal = $this->isModalSubmit($request);
            
            // Capture old values before changes
            $oldValues = [
                'titre' => $rec->getTitre(),
                'categorie' => $rec->getCategorie(),
                'projet' => $rec->getProjet(),
                'statut' => strtolower(trim((string) $rec->getStatut())),
                'id_user' => $rec->getIdUser()
            ];
            
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
            $this->attachUploadedFileIfAny($rec, $request);
            
            // Capture new values and track all changes
            $newValues = [
                'titre' => $rec->getTitre(),
                'categorie' => $rec->getCategorie(),
                'projet' => $rec->getProjet(),
                'statut' => strtolower(trim((string) $rec->getStatut())),
                'id_user' => $rec->getIdUser()
            ];
            
            $changes = [];
            foreach ($oldValues as $field => $oldValue) {
                $newValue = $newValues[$field];
                if ($oldValue !== $newValue) {
                    $changes[$field] = [
                        'old' => $oldValue,
                        'new' => $newValue
                    ];
                }
            }
            
            // Log changes if any
            if (!empty($changes)) {
                if (isset($changes['statut'])) {
                    // Status change is special - log it separately
                    $this->historyService->logActivity((int) $rec->getIdRec(), 'status_change', [
                        'old_status' => $changes['statut']['old'],
                        'new_status' => $changes['statut']['new']
                    ]);
                    
                    // Remove status from general changes to avoid duplication
                    unset($changes['statut']);
                }
                
                if (!empty($changes)) {
                    // Log other field changes
                    $this->historyService->logActivity((int) $rec->getIdRec(), 'update', $changes);
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
        $rec = $this->reclamationRepository->find($id);
        if (!$rec instanceof Reclamation) {
            throw $this->createNotFoundException();
        }

        return $this->attachmentResponse($rec);
    }

    #[Route('/{id}/delete', name: 'admin_reclamations_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function delete(Request $request, int $id): Response
    {
        $this->checkCsrf($request);
        $rec = $this->reclamationRepository->find($id);
        if (!$rec instanceof Reclamation) {
            throw $this->createNotFoundException('Reclamation not found.');
        }

        // Log reclamation deletion before removing
        $this->historyService->logActivity((int) $rec->getIdRec(), 'delete', [
            'titre' => $rec->getTitre(),
            'categorie' => $rec->getCategorie(),
            'statut' => $rec->getStatut(),
            'assigned_user' => $rec->getIdUser()
        ]);

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

    private function attachUploadedFileIfAny(Reclamation $rec, Request $request): void
    {
        $binary = $this->uploadedAttachment($request);
        if ($binary !== null && $rec->getIdRec() !== null) {
            $this->storeAttachment($rec, $binary);
        }
    }

}
