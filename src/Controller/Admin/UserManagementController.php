<?php

namespace App\Controller\Admin;

use App\Controller\Trait\ValidationFlashTrait;
use App\Entity\Dto\Admin\AdminUserWriteInput;
use App\Entity\UserHandling\Utilisateur;
use App\Repository\UserHandling\UtilisateurRepository;
use App\Service\AdminPdfExportService;
use App\Service\AuthService;
use App\Service\MailService;
use Doctrine\DBAL\Exception\TableNotFoundException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/admin')]
class UserManagementController extends AbstractController
{
    use ValidationFlashTrait;

    public function __construct(
        private readonly AuthService $authService,
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

    private function isModalSubmit(Request $request): bool
    {
        return $request->isMethod('POST') && $request->request->getBoolean('_modal');
    }

    private function wantsFormFragment(Request $request): bool
    {
        return $request->query->getBoolean('fragment');
    }

    private function userFormPartialResponse(string $mode, ?Utilisateur $user, int $status = Response::HTTP_OK): Response
    {
        return new Response(
            $this->renderView('admin/user/_form_inner.html.twig', [
                'mode' => $mode,
                'user' => $user,
                'modal' => true,
            ]),
            $status
        );
    }

    #[Route('', name: 'admin_home', methods: ['GET'])]
    public function home(): Response
    {
        if ($r = $this->ensureAdmin()) {
            return $r;
        }

        return $this->redirectToRoute('admin_users_index');
    }

    #[Route('/users', name: 'admin_users_index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        if ($r = $this->ensureAdmin()) {
            return $r;
        }

        $sort = (string) $request->query->get('sort', 'nom');
        $dir = strtoupper((string) $request->query->get('dir', 'ASC')) === 'DESC' ? 'DESC' : 'ASC';

        $chartRoles = $this->utilisateurRepository->getChartDataByRole();
        $chartStatuts = $this->utilisateurRepository->getChartDataByStatut();

        return $this->render('admin/user/index.html.twig', [
            'stats' => $this->utilisateurRepository->getAdminUserStats(),
            'chart_roles' => $chartRoles,
            'chart_statuts' => $chartStatuts,
            'charts_json' => json_encode(
                ['roles' => $chartRoles, 'statuts' => $chartStatuts],
                \JSON_HEX_TAG | \JSON_HEX_APOS | \JSON_HEX_AMP | \JSON_HEX_QUOT
            ),
            'users' => $this->utilisateurRepository->findForAdminListing(null, $sort, $dir),
            'sort' => $sort,
            'dir' => $dir,
        ]);
    }

    #[Route('/users/export.pdf', name: 'admin_users_export_pdf', methods: ['GET'])]
    public function exportUsersPdf(Request $request, AdminPdfExportService $pdfExport): Response
    {
        if ($r = $this->ensureAdmin()) {
            return $r;
        }

        $sort = (string) $request->query->get('sort', 'nom');
        $dir = strtoupper((string) $request->query->get('dir', 'ASC')) === 'DESC' ? 'DESC' : 'ASC';

        return $pdfExport->renderTablePdf(
            'admin/export/users_pdf.html.twig',
            [
                'users' => $this->utilisateurRepository->findForAdminListing(null, $sort, $dir),
                'stats' => $this->utilisateurRepository->getAdminUserStats(),
                'generated_at' => new \DateTimeImmutable(),
                'sort' => $sort,
                'dir' => $dir,
            ],
            'nexum-users-' . (new \DateTimeImmutable())->format('Y-m-d') . '.pdf',
        );
    }

    #[Route('/users/new', name: 'admin_users_new', methods: ['GET', 'POST'])]
    public function newUser(Request $request): Response
    {
        if ($r = $this->ensureAdmin()) {
            return $r;
        }

        if ($request->isMethod('POST')) {
            $modal = $this->isModalSubmit($request);
            $email = trim((string) $request->request->get('email', ''));

            $dto = AdminUserWriteInput::fromRequest($request, true);
            if ($this->flashValidationErrors($this->validator->validate($dto))) {
                return $modal
                    ? $this->userFormPartialResponse('create', null, Response::HTTP_UNPROCESSABLE_ENTITY)
                    : $this->render('admin/user/form.html.twig', [
                        'mode' => 'create',
                        'user' => null,
                    ]);
            }

            if ($this->utilisateurRepository->findByEmail($email)) {
                $this->addFlash('error', 'This email is already registered.');

                return $modal
                    ? $this->userFormPartialResponse('create', null, Response::HTTP_UNPROCESSABLE_ENTITY)
                    : $this->render('admin/user/form.html.twig', [
                        'mode' => 'create',
                        'user' => null,
                    ]);
            }

            $utilisateur = new Utilisateur();
            $this->fillUserFromRequest($utilisateur, $request, true);
            $utilisateur->setDateInscription(new \DateTime());
            $utilisateur->setScore(100);
            $this->attachUploadedImageIfAny($utilisateur, $request, false);
            $temporaryPassword = trim((string) $request->request->get('_password', ''));

            $this->entityManager->persist($utilisateur);
            $this->entityManager->flush();

            if ($temporaryPassword !== '') {
                try {
                    $this->mailService->sendInvitationEmail(
                        (string) $utilisateur->getEmail(),
                        (string) ($utilisateur->getPrenom() ?? $utilisateur->getNom() ?? 'Utilisateur'),
                        $temporaryPassword
                    );
                } catch (\Throwable) {
                    $this->addFlash('warning', 'User created but invitation email could not be sent.');
                }
            }

            $this->addFlash('success', 'User created successfully.');

            return $modal
                ? new JsonResponse(['ok' => true, 'redirect' => $this->generateUrl('admin_users_index')])
                : $this->redirectToRoute('admin_users_index');
        }

        if ($this->wantsFormFragment($request)) {
            return $this->userFormPartialResponse('create', null);
        }

        return $this->render('admin/user/form.html.twig', [
            'mode' => 'create',
            'user' => null,
        ]);
    }

    #[Route('/users/{id}/edit', name: 'admin_users_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function edit(Request $request, int $id): Response
    {
        if ($r = $this->ensureAdmin()) {
            return $r;
        }

        $utilisateur = $this->utilisateurRepository->find($id);
        if (!$utilisateur instanceof Utilisateur) {
            throw $this->createNotFoundException('User not found.');
        }

        if ($request->isMethod('POST')) {
            $modal = $this->isModalSubmit($request);
            $email = trim((string) $request->request->get('email', ''));
            $oldStatus = strtolower(trim((string) $utilisateur->getStatut()));

            $dto = AdminUserWriteInput::fromRequest($request, false);
            if ($this->flashValidationErrors($this->validator->validate($dto))) {
                return $modal
                    ? $this->userFormPartialResponse('edit', $utilisateur, Response::HTTP_UNPROCESSABLE_ENTITY)
                    : $this->render('admin/user/form.html.twig', [
                        'mode' => 'edit',
                        'user' => $utilisateur,
                    ]);
            }

            if ($this->utilisateurRepository->existsOtherUserWithEmail($email, $utilisateur->getId())) {
                $this->addFlash('error', 'This email is already used by another account.');

                return $modal
                    ? $this->userFormPartialResponse('edit', $utilisateur, Response::HTTP_UNPROCESSABLE_ENTITY)
                    : $this->render('admin/user/form.html.twig', [
                        'mode' => 'edit',
                        'user' => $utilisateur,
                    ]);
            }

            $this->fillUserFromRequest($utilisateur, $request, false);
            $this->entityManager->flush();
            $newStatus = strtolower(trim((string) $utilisateur->getStatut()));

            if ($oldStatus !== $newStatus && trim((string) $utilisateur->getEmail()) !== '') {
                try {
                    $this->mailService->sendStatusChangeEmail((string) $utilisateur->getEmail(), $newStatus);
                } catch (\Throwable) {
                    $this->addFlash('warning', 'User updated, but status notification email could not be sent.');
                }
            }

            $this->attachUploadedImageIfAny($utilisateur, $request, true);

            $this->addFlash('success', 'User updated successfully.');

            return $modal
                ? new JsonResponse(['ok' => true, 'redirect' => $this->generateUrl('admin_users_index')])
                : $this->redirectToRoute('admin_users_index');
        }

        if ($this->wantsFormFragment($request)) {
            return $this->userFormPartialResponse('edit', $utilisateur);
        }

        return $this->render('admin/user/form.html.twig', [
            'mode' => 'edit',
            'user' => $utilisateur,
        ]);
    }

    #[Route('/users/{id}/delete', name: 'admin_users_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function delete(Request $request, int $id): Response
    {
        if ($r = $this->ensureAdmin()) {
            return $r;
        }

        $utilisateur = $this->utilisateurRepository->find($id);
        if (!$utilisateur instanceof Utilisateur) {
            throw $this->createNotFoundException('User not found.');
        }

        if ($utilisateur->getId() === $this->authService->getCurrentUserId()) {
            $this->addFlash('error', 'You cannot delete your own account.');

            return $this->redirectToRoute('admin_users_index');
        }

        $this->deleteResourceAssignmentsSql((int) $utilisateur->getId());

        $this->entityManager->remove($utilisateur);
        $this->entityManager->flush();
        $this->addFlash('success', 'User deleted.');

        return $this->redirectToRoute('admin_users_index');
    }

    #[Route('/users/{id}/activate', name: 'admin_users_activate', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function activate(int $id): Response
    {
        if ($r = $this->ensureAdmin()) {
            return $r;
        }

        $utilisateur = $this->utilisateurRepository->find($id);
        if (!$utilisateur instanceof Utilisateur) {
            throw $this->createNotFoundException('User not found.');
        }

        if (strtolower((string) $utilisateur->getStatut()) === 'active') {
            $this->addFlash('info', 'This user is already active.');

            return $this->redirectToRoute('admin_users_index');
        }

        $utilisateur->setStatut('active');
        $this->entityManager->flush();
        if (trim((string) $utilisateur->getEmail()) !== '') {
            try {
                $this->mailService->sendStatusChangeEmail((string) $utilisateur->getEmail(), 'active');
            } catch (\Throwable) {
                $this->addFlash('warning', 'User activated, but status email could not be sent.');
            }
        }
        $this->addFlash('success', 'User activated.');

        return $this->redirectToRoute('admin_users_index');
    }

    /**
     * Remove resource rows without loading the ORM collection (avoids errors when resource_assignment is not migrated).
     */
    private function deleteResourceAssignmentsSql(int $userId): void
    {
        try {
            $this->entityManager->getConnection()->executeStatement(
                'DELETE FROM resource_assignment WHERE user_id = ?',
                [$userId]
            );
        } catch (TableNotFoundException) {
            // Schema has no resource module table yet
        }
    }

    private function fillUserFromRequest(Utilisateur $utilisateur, Request $request, bool $isNew): void
    {
        $utilisateur->setNom(trim((string) $request->request->get('nom', '')));
        $utilisateur->setPrenom(trim((string) $request->request->get('prenom', '')));
        $utilisateur->setEmail(trim((string) $request->request->get('email', '')));
        $telephone = trim((string) $request->request->get('telephone', ''));
        $utilisateur->setTelephone($telephone !== '' ? $telephone : null);
        $departement = trim((string) $request->request->get('departement', ''));
        $utilisateur->setDepartement($departement !== '' ? $departement : null);
        $utilisateur->setRole(strtolower(trim((string) $request->request->get('role', ''))));
        $utilisateur->setStatut(strtolower(trim((string) $request->request->get('statut', 'active'))));

        $password = (string) $request->request->get('_password', '');
        if ($isNew || $password !== '') {
            $utilisateur->setPassword($password);
        }
    }

    /**
     * @param bool $isExistingEntity When true, run a direct SQL UPDATE for the blob after flush (Doctrine BLOB quirk).
     */
    private function attachUploadedImageIfAny(Utilisateur $utilisateur, Request $request, bool $isExistingEntity): void
    {
        $file = $request->files->get('imagelink');
        if (!$file instanceof UploadedFile || $file->getError() !== UPLOAD_ERR_OK) {
            return;
        }

        $path = $file->getRealPath() ?: $file->getPathname();
        $binary = @file_get_contents($path);
        if ($binary === false || $binary === '') {
            $this->addFlash('warning', 'Profile image could not be read.');

            return;
        }

        if ($isExistingEntity) {
            $this->entityManager->getConnection()->executeStatement(
                'UPDATE utilisateurs SET imagelink = ? WHERE id = ?',
                [$binary, $utilisateur->getId()]
            );
        }

        $utilisateur->setImagelink($binary);

        if ($utilisateur->getId() === $this->authService->getCurrentUserId()) {
            $this->authService->refreshSessionUser($utilisateur, true);
        }
    }
}
