<?php

namespace App\Controller\ResourcesManagement;

use App\Attribute\RequireAdmin;
use App\Entity\ResourcesManagement\ResourceAssignment;
use App\Entity\ResourcesManagement\Resource;
use App\Entity\UserHandling\Utilisateur;
use App\Service\Pdf\RequestPdfService;
use App\Service\ResourcesManagement\ResourceStockService;
use App\Repository\ResourcesManagement\ResourceAssignmentRepository;
use App\Repository\ResourcesManagement\ResourceRepository;
use App\Repository\Projects\ProjectAssignmentRepository;
use App\Repository\Projects\ProjectRepository;
use App\Service\AuthService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/client/resources')]
class ClientResourceController extends AbstractController
{
    private const SPAM_REQUEST_LIMIT = 3;

    public function __construct(private readonly AuthService $authService)
    {
    }

    #[Route('/', name: 'client_resources_view', methods: ['GET'])]
    public function view(
        ResourceAssignmentRepository $assignmentRepository,
        ResourceRepository $resourceRepository,
        EntityManagerInterface $em
    ): Response
    {
        $userId = $this->authService->getCurrentUserId();
        if ($userId === null) {
            throw $this->createAccessDeniedException('You must be logged in to view your resources.');
        }

        $user = $em->getRepository(Utilisateur::class)->find($userId);
        if (!$user) {
            throw $this->createNotFoundException("User with ID $userId not found.");
        }

        $assignments = $assignmentRepository->findBy(['utilisateur' => $user]);

        $resourceData = [];
        foreach ($assignments as $assignment) {
            $resource = $resourceRepository->find($assignment->getResourceId());
            if (!$resource) continue;

            $resourceData[] = [
                'assignment' => $assignment,
                'resource' => $resource
            ];
        }

        return $this->render('resources-management/client-resources.html.twig', [
            'resourceData' => $resourceData,
            'emptyMessage' => count($resourceData) === 0 ? 'No resources assigned.' : null
        ]);
    }

    #[Route('/request', name: 'request_resource')]
public function requestResource(
    ResourceRepository $resourceRepository,
    ProjectRepository $projectRepository,
    ProjectAssignmentRepository $projectAssignmentRepository,
    ResourceAssignmentRepository $assignmentRepository,
    EntityManagerInterface $em
): Response {

    // Get available resources
    $resources = $resourceRepository->createQueryBuilder('r')
        ->where('r.available_quantity > 0')
        ->getQuery()
        ->getResult();

    // Get user
    $userId = $this->authService->getCurrentUserId();
    if ($userId === null) {
        throw $this->createAccessDeniedException('You must be logged in to request a resource.');
    }

    $user = $em->getRepository(Utilisateur::class)->find($userId);
    if (!$user) {
        throw $this->createNotFoundException("User with ID $userId not found.");
    }

    // Projects the user belongs to: the same rule submitRequest enforces.
    $projects = array_values($projectRepository->findIndexedByIds(
        $this->requestableProjectIds((int) $user->getId(), $projectRepository, $projectAssignmentRepository)
    ));

    $riskScore = $this->riskScore($assignmentRepository, $user);
    $recentRequestCount = $this->getPendingSpamRequestCount($assignmentRepository, $user);
    // Worked out on every visit: nothing is stored on the user, so the block ends when the pending requests are handled.
    $isBlocked = $riskScore > 50 || ($recentRequestCount + 1) >= self::SPAM_REQUEST_LIMIT; // same rule as submitRequest

    // ==========================
    // RETURN VIEW
    // ==========================

    return $this->render('resources-management/request-resource.html.twig', [
        'resources' => $resources,
        'projects' => $projects,
        'isBlocked' => $isBlocked,
        'riskScore' => round($riskScore, 2),
        'recentRequestCount' => $recentRequestCount
    ]);
}

    #[Route('/request/submit', name: 'submit_resource_request', methods: ['POST'])]
public function submitRequest(
    Request $request,
    EntityManagerInterface $em,
    ResourceRepository $resourceRepository,
    ProjectRepository $projectRepository,
    ProjectAssignmentRepository $projectAssignmentRepository,
    ResourceAssignmentRepository $assignmentRepository,
    RequestPdfService $pdfService,
    ResourceStockService $stock
): Response {

    // 🔐 USER
    $userId = $this->authService->getCurrentUserId();
    if ($userId === null) {
        throw $this->createAccessDeniedException('You must be logged in.');
    }

    if (!$this->isCsrfTokenValid('submit_resource_request', (string) $request->request->get('_token'))) {
        $this->addFlash('error', 'Invalid CSRF token. Please reload the page and try again.');
        return $this->redirectToRoute('request_resource');
    }

    $resourceId = $request->request->getInt('resource_id');
    $projectId = $request->request->getInt('project_id');
    $quantity = $request->request->getInt('quantity');

    $resource = $resourceRepository->find($resourceId);
    $project = $projectRepository->find($projectId);

    if (!$resource || !$project || $quantity < 1 || $quantity > $resource->getAvailableQuantity()) {
        $this->addFlash('error', 'Invalid resource or quantity.');
        return $this->redirectToRoute('request_resource');
    }

    // The request is charged to a project, so the user must belong to it.
    if (!in_array((int) $project->getId(), $this->requestableProjectIds((int) $userId, $projectRepository, $projectAssignmentRepository), true)) {
        $this->addFlash('error', 'You can only request resources for your own projects.');
        return $this->redirectToRoute('request_resource');
    }

    // The return date is the project end date, so a project without one cannot be used.
    if ($project->getEndDate() === null) {
        $this->addFlash('error', 'This project has no end date, so a return date cannot be set. Ask a manager to set one.');
        return $this->redirectToRoute('request_resource');
    }

    $user = $em->getRepository(Utilisateur::class)->find($userId);
    if (!$user) {
        throw $this->createNotFoundException("User not found.");
    }

    // 🎯 SCORE LOGIC
    $score = $user->getScore();

    if ($this->riskScore($assignmentRepository, $user) > 50) {
        $this->addFlash('error', 'Your account is blocked from requesting resources.');
        return $this->redirectToRoute('request_resource');
    }

    if (($this->getPendingSpamRequestCount($assignmentRepository, $user) + 1) >= self::SPAM_REQUEST_LIMIT) {
        $this->addFlash('error', 'Too many pending resource requests. Wait for a decision on them first.');

        return $this->redirectToRoute('request_resource');
    }

    // count accepted resources
    $acceptedCount = $assignmentRepository->count([
        'utilisateur' => $user,
        'status' => 'ACCEPTED'
    ]);

    // default status
    $status = 'PENDING';

    if ($score == 0) {
        $status = 'DECLINED';
    } 
    elseif ($score >= 100 && $acceptedCount >= 3) {
        $status = 'ACCEPTED'; // auto-approve
    }

    // 🧾 CREATE ASSIGNMENT
    $assignment = new ResourceAssignment();
    $assignment->setResourceId($resourceId);
    $assignment->setProjectCode((string)$project->getId());
    $assignment->setQuantity($quantity);
    $assignment->setAssignmentDate(new \DateTime());
    $assignment->setStatus($status);
    $unitCost = (float) ($resource->getUnitCost() ?? 0);
    $totalCost = number_format($unitCost * $quantity, 2, '.', '');
    $assignment->setTotalCost($totalCost);

    // 🔥 IMPORTANT: RETURN DATE = PROJECT END DATE
    $assignment->setReturnDate($project->getEndDate());

    $assignment->setUtilisateur($user);
    $assignment->setPenaltyDaysApplied(0);
    $assignment->setBonusApplied(false);

    // Auto accepted requests take their stock now; the row is locked so two requests cannot both take the last units.
    $em->wrapInTransaction(function () use ($em, $assignment, $resource, $stock): void {
        $stock->lock($resource);
        if ($stock->holdsStock($assignment) && $assignment->getQuantity() > $resource->getAvailableQuantity()) {
            $assignment->setStatus('PENDING');
        }
        $em->persist($assignment);
        $em->flush();
        $stock->recalculate($resource);
        $em->flush();
    });

    // 📄 PDF
    $pdfData = [
        'user' => $user->getNom() . ' ' . $user->getPrenom(),
        'resource' => $resource->getResourceName(),
        'quantity' => $quantity,
        'cost' => $totalCost,
        'date' => (new \DateTime())->format('Y-m-d H:i:s')
    ];

    $pdfContent = $pdfService->generate($pdfData);

    $fileName = 'request_'.$assignment->getResourceId().'.pdf';

    return new Response($pdfContent, 200, [
        'Content-Type' => 'application/pdf',
        'Content-Disposition' => 'attachment; filename="'.$fileName.'"'
    ]);
}
    // Manage pending requests
    #[Route('/admin/requests', name: 'manage_requests')]
    #[RequireAdmin]
    public function manageRequests(
        ResourceAssignmentRepository $assignmentRepository,
        ResourceRepository $resourceRepository
    ): Response {
        $assignments = $assignmentRepository->findBy(['status' => 'PENDING']);
        $data = [];

        foreach ($assignments as $assignment) {
            $resource = $resourceRepository->find($assignment->getResourceId());
            $data[] = [
                'assignment' => $assignment,
                'resource' => $resource
            ];
        }

        return $this->render('resources-management/manage-requests.html.twig', [
            'data' => $data
        ]);
    }

    #[Route('/admin/request/{id}/accept', name: 'accept_request', methods: ['POST'])]
    #[RequireAdmin]
public function accept(
    int $id,
    Request $request,
    ResourceAssignmentRepository $repo,
    ResourceRepository $resourceRepository,
    EntityManagerInterface $em,
    ResourceStockService $stock
): Response {

    if (!$this->isCsrfTokenValid('request_decision', (string) $request->request->get('_token'))) {
        $this->addFlash('error', 'Invalid CSRF token.');
        return $this->redirectToRoute('manage_requests');
    }

    $assignment = $repo->find($id);

    if (!$assignment || !$assignment->getUtilisateur()) {
        $this->addFlash('error', 'Request not found or client missing.');
        return $this->redirectToRoute('manage_requests');
    }

    $client = $assignment->getUtilisateur();

    $resource = $resourceRepository->find($assignment->getResourceId());

    if (!$resource) {
        $this->addFlash('error', 'Resource not found.');
        return $this->redirectToRoute('manage_requests');
    }

    // Accepting takes stock, so it runs once, under a lock, and only for a request that is still pending.
    $outcome = $em->wrapInTransaction(function () use ($em, $assignment, $resource, $stock): string {
        $stock->lock($resource);
        $em->refresh($assignment);

        if ($assignment->getStatus() !== 'PENDING') {
            return 'processed';
        }
        if ($resource->getAvailableQuantity() < $assignment->getQuantity()) {
            return 'stock';
        }

        $assignment->setStatus('ACCEPTED');
        $em->flush();
        $stock->recalculate($resource);
        $em->flush();

        return 'accepted';
    });

    if ($outcome === 'processed') {
        $this->addFlash('info', 'This request was already processed.');
        return $this->redirectToRoute('manage_requests');
    }
    if ($outcome === 'stock') {
        $this->addFlash('error', 'Not enough stock available.');
        return $this->redirectToRoute('manage_requests');
    }

    $this->addFlash('success', 'Request accepted.');

    return $this->redirectToRoute('manage_requests');
}
    #[Route('/admin/request/{id}/decline', name: 'decline_request', methods: ['POST'])]
    #[RequireAdmin]
    public function decline(
        int $id,
        Request $request,
        ResourceAssignmentRepository $repo,
        EntityManagerInterface $em,
        ResourceRepository $resourceRepository,
            ResourceStockService $stock
    ): Response {
        if (!$this->isCsrfTokenValid('request_decision', (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Invalid CSRF token.');
            return $this->redirectToRoute('manage_requests');
        }

        $assignment = $repo->find($id);
        if (!$assignment || !$assignment->getUtilisateur()) {
            $this->addFlash('error', 'Request not found or client missing.');
            return $this->redirectToRoute('manage_requests');
        }

        $resource = $resourceRepository->find($assignment->getResourceId());

        $em->wrapInTransaction(function () use ($em, $assignment, $resource, $stock): void {
            if ($resource) {
                $stock->lock($resource);
            }
            $assignment->setStatus('DECLINED');
            $em->flush();
            if ($resource) {
                $stock->recalculate($resource);
                $em->flush();
            }
        });

        $this->addFlash('success', 'Request declined.');

        return $this->redirectToRoute('manage_requests');
    }
    // --- NEW DELETE route for front page ---
    #[Route('/assignment/{id}/delete', name: 'delete_assignment', methods: ['POST'])]
    public function delete(
        int $id,
        Request $request,
        ResourceAssignmentRepository $repo,
        EntityManagerInterface $em,
        ResourceRepository $resourceRepository,
        ResourceStockService $stock
    ): Response {
        $assignment = $repo->find($id);
        if ($assignment) {
            $this->denyUnlessOwnerOrAdmin($assignment);
            if (!$this->isCsrfTokenValid('delete'.$id, (string) $request->request->get('_token'))) {
                $this->addFlash('error', 'Invalid CSRF token.');
                return $this->redirectToRoute('client_resources_view');
            }

            $resource = $resourceRepository->find($assignment->getResourceId());
            $em->wrapInTransaction(function () use ($em, $assignment, $resource, $stock): void {
                if ($resource) {
                    $stock->lock($resource);
                }
                $em->remove($assignment);
                $em->flush();
                if ($resource) {
                    $stock->recalculate($resource);
                    $em->flush();
                }
            });
        }
        return $this->redirectToRoute('client_resources_view');
    }

    // --- NEW EDIT route placeholder ---
    #[Route('/assignment/{id}/edit', name: 'edit_assignment', methods: ['POST'])]
public function edit(
    int $id,
    Request $request,
    ResourceAssignmentRepository $repo,
    EntityManagerInterface $em,
    ResourceRepository $resourceRepository,
    ResourceStockService $stock
): Response
{
    $assignment = $repo->find($id);
    if (!$assignment) {
        throw $this->createNotFoundException("Assignment with ID $id not found.");
    }
    $this->denyUnlessOwnerOrAdmin($assignment);

    // CSRF check
    $submittedToken = $request->request->get('_token');
    if (!$this->isCsrfTokenValid('edit'.$id, is_string($submittedToken) ? $submittedToken : null)) {
        $this->addFlash('error', 'Invalid CSRF token.');
        return $this->redirectToRoute('client_resources_view');
    }

    // Get submitted values
    $newQuantity = (int) $request->request->get('quantity');
    $newReturnDate = $request->request->get('returnDate');

    $resource = $resourceRepository->find($assignment->getResourceId());
    if (!$resource) {
        $this->addFlash('error', 'Resource not found.');
        return $this->redirectToRoute('client_resources_view');
    }

    // Only requests the user can still change (the page shows the form for these two states).
    if (!in_array($assignment->getStatus(), ['PENDING', 'DECLINED'], true)) {
        $this->addFlash('error', 'Only pending or declined requests can be edited.');
        return $this->redirectToRoute('client_resources_view');
    }

    $returnDate = null;
    if (is_string($newReturnDate) && $newReturnDate !== '') {
        $returnDate = \DateTime::createFromFormat('!Y-m-d', $newReturnDate) ?: null;
        if ($returnDate === null) {
            $this->addFlash('error', 'Invalid return date.');
            return $this->redirectToRoute('client_resources_view');
        }
    }

    $error = $em->wrapInTransaction(function () use ($em, $assignment, $resource, $stock, $newQuantity, $returnDate): ?string {
        $stock->lock($resource);
        $available = (int) $resource->getAvailableQuantity();
        if ($newQuantity < 1 || $newQuantity > $available) {
            return 'Quantity must be between 1 and '.$available;
        }

        $assignment->setQuantity($newQuantity);
        $assignment->setReturnDate($returnDate);
        $assignment->setStatus('PENDING'); // goes back to the admin for approval
        $em->flush();
        $stock->recalculate($resource);
        $em->flush();

        return null;
    });

    if ($error !== null) {
        $this->addFlash('error', $error);
        return $this->redirectToRoute('client_resources_view');
    }

    $this->addFlash('success', 'Assignment updated successfully.');

    return $this->redirectToRoute('client_resources_view');
}

    /**
     * Projects a user may request resources for: the ones they created, are assigned to, or are a member of.
     *
     * @return int[]
     */
    private function requestableProjectIds(
        int $userId,
        ProjectRepository $projectRepository,
        ProjectAssignmentRepository $projectAssignmentRepository
    ): array {
        return array_values(array_unique(array_merge(
            $projectRepository->getProjectIdsForUser($userId),
            $projectAssignmentRepository->getProjectIdsByUserId($userId),
        )));
    }

    /** An assignment may only be changed by the user it belongs to, or by an administrator. */
    private function denyUnlessOwnerOrAdmin(ResourceAssignment $assignment): void
    {
        $userId = $this->authService->getCurrentUserId();
        if ($userId === null) {
            throw $this->createAccessDeniedException('You must be logged in.');
        }

        if ($assignment->getUtilisateur()?->getId() !== $userId && !$this->authService->isAdmin()) {
            throw $this->createAccessDeniedException('This assignment belongs to another user.');
        }
    }

    /** Requested quantity and request count, divided by the user's score (a score of 0 counts as 1). */
    private function riskScore(ResourceAssignmentRepository $assignmentRepository, Utilisateur $user): float
    {
        $requests = $assignmentRepository->findBy(['utilisateur' => $user]);
        $totalQty = array_sum(array_map(static fn ($r) => $r->getQuantity(), $requests));

        return (($totalQty * 0.5) + (count($requests) * 2)) / max(1, (int) $user->getScore());
    }

    private function getPendingSpamRequestCount(
        ResourceAssignmentRepository $assignmentRepository,
        Utilisateur $user
    ): int {
        return $assignmentRepository->countPendingRequestsByUser($user);
    }
}
