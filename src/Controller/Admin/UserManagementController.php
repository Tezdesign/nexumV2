<?php

namespace App\Controller\Admin;

use App\Controller\Trait\ProfilePhotoTrait;
use App\Controller\Trait\ValidationFlashTrait;
use App\Entity\Dto\Admin\AdminUserWriteInput;
use App\Entity\UserHandling\Utilisateur;
use App\Repository\UserHandling\UtilisateurRepository;
use App\Service\AdminPdfExportService;
use App\Service\AuthService;
use Doctrine\DBAL\Exception\ForeignKeyConstraintViolationException;
use Doctrine\DBAL\Exception\TableNotFoundException;
use Doctrine\ORM\EntityManagerInterface;
use App\Attribute\RequireAdmin;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/admin')]
#[RequireAdmin]
class UserManagementController extends AbstractController
{
    use ProfilePhotoTrait;
    use ValidationFlashTrait;

    public function __construct(
        private readonly AuthService $authService,
        private readonly UtilisateurRepository $utilisateurRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly ValidatorInterface $validator,
    ) {
    }

    private function checkCsrf(Request $request): void
    {
        if (!$this->isCsrfTokenValid('admin_users', (string) $request->request->get('_token'))) {
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
        return $this->redirectToRoute('admin_users_index');
    }

    #[Route('/users', name: 'admin_users_index', methods: ['GET'])]
    public function index(Request $request): Response
    {
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
        if ($request->isMethod('POST')) {
            $this->checkCsrf($request);
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

            $this->entityManager->persist($utilisateur);
            $this->entityManager->flush();

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
        $utilisateur = $this->utilisateurRepository->find($id);
        if (!$utilisateur instanceof Utilisateur) {
            throw $this->createNotFoundException('User not found.');
        }

        if ($request->isMethod('POST')) {
            $this->checkCsrf($request);
            $modal = $this->isModalSubmit($request);
            $email = trim((string) $request->request->get('email', ''));

            $dto = AdminUserWriteInput::fromRequest($request, false);
            if ($this->flashValidationErrors($this->validator->validate($dto))) {
                return $modal
                    ? $this->userFormPartialResponse('edit', $utilisateur, Response::HTTP_UNPROCESSABLE_ENTITY)
                    : $this->render('admin/user/form.html.twig', [
                        'mode' => 'edit',
                        'user' => $utilisateur,
                    ]);
            }

            $utilisateurId = $utilisateur->getId();
            if ($utilisateurId !== null && $this->utilisateurRepository->existsOtherUserWithEmail($email, $utilisateurId)) {
                $this->addFlash('error', 'This email is already used by another account.');

                return $modal
                    ? $this->userFormPartialResponse('edit', $utilisateur, Response::HTTP_UNPROCESSABLE_ENTITY)
                    : $this->render('admin/user/form.html.twig', [
                        'mode' => 'edit',
                        'user' => $utilisateur,
                    ]);
            }

            $lockout = $this->lockoutReason($utilisateur, $dto->role, $dto->statut);
            if ($lockout !== null) {
                $this->addFlash('error', $lockout);

                return $modal
                    ? $this->userFormPartialResponse('edit', $utilisateur, Response::HTTP_UNPROCESSABLE_ENTITY)
                    : $this->render('admin/user/form.html.twig', [
                        'mode' => 'edit',
                        'user' => $utilisateur,
                    ]);
            }

            $this->fillUserFromRequest($utilisateur, $request, false);
            $this->entityManager->flush();

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
        $this->checkCsrf($request);
        $utilisateur = $this->utilisateurRepository->find($id);
        if (!$utilisateur instanceof Utilisateur) {
            throw $this->createNotFoundException('User not found.');
        }

        if ($lockout = $this->lockoutReason($utilisateur, '', 'deleted')) {
            $this->addFlash('error', $lockout);

            return $this->redirectToRoute('admin_users_index');
        }

        try {
            $this->entityManager->wrapInTransaction(function () use ($utilisateur): void {
                $this->deleteResourceAssignmentsSql((int) $utilisateur->getId());
                $this->entityManager->remove($utilisateur);
            });
            $this->addFlash('success', 'User deleted.');
        } catch (ForeignKeyConstraintViolationException) {
            // Nothing was removed: the transaction rolled back. Keep the history, block the account instead.
            $this->addFlash('error', 'This user still has tasks, messages or other records. Set the status to pending instead of deleting.');
        }

        return $this->redirectToRoute('admin_users_index');
    }

    #[Route('/users/{id}/activate', name: 'admin_users_activate', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function activate(Request $request, int $id): Response
    {
        $this->checkCsrf($request);
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
        $this->addFlash('success', 'User activated.');

        return $this->redirectToRoute('admin_users_index');
    }

    /**
     * Why $user may not become $newRole / $newStatus ("deleted" for a deletion), or null when it is fine.
     * An admin cannot take their own admin access away, and the last active admin cannot be removed.
     */
    private function lockoutReason(Utilisateur $user, string $newRole, string $newStatus): ?string
    {
        $staysActiveAdmin = str_contains($newRole, 'admin') && in_array($newStatus, ['active', 'actif'], true);
        if ($staysActiveAdmin) {
            return null;
        }

        if ($user->getId() === $this->authService->getCurrentUserId()) {
            return $newStatus === 'deleted'
                ? 'You cannot delete your own account.'
                : 'You cannot remove your own administrator access or deactivate your own account.';
        }

        $wasActiveAdmin = str_contains(strtolower((string) $user->getRole()), 'admin')
            && in_array(strtolower((string) $user->getStatut()), ['active', 'actif'], true);
        if ($wasActiveAdmin && $this->utilisateurRepository->countOtherActiveAdmins((int) $user->getId()) === 0) {
            return 'This is the last active administrator. Make someone else an administrator first.';
        }

        return null;
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
        $binary = $this->uploadedPhoto($request->files->get('imagelink'));
        if ($binary === null) {
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
