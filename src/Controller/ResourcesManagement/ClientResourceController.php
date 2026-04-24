<?php

namespace App\Controller\ResourcesManagement;

use App\Entity\ResourcesManagement\ResourceAssignment;
use App\Entity\ResourcesManagement\Resource;
use App\Entity\UserHandling\Utilisateur;
use App\Service\Pdf\RequestPdfService;
use App\Repository\ResourcesManagement\ResourceAssignmentRepository;
use App\Repository\ResourcesManagement\ResourceRepository;
use App\Repository\Projects\ProjectRepository;
use App\Service\AuthService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

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

    // Get user projects
    $projects = $projectRepository->findBy(['assigned_to' => $user->getId()]);

    // ==========================
    // FRAUD / RISK DETECTION (FIXED)
    // ==========================

    $allRequests = $assignmentRepository->findBy([
        'utilisateur' => $user
    ]);

    // total quantity requested
    $totalQty = array_sum(
        array_map(fn($r) => $r->getQuantity(), $allRequests)
    );

    // accepted requests count
    $acceptedCount = array_reduce($allRequests, function ($carry, $r) {
        return $carry + ($r->getStatus() === 'ACCEPTED' ? 1 : 0);
    }, 0);

    // safer score base
    $baseScore = ($totalQty * 0.5) + (count($allRequests) * 2);

    // avoid division by zero / abuse of score=0
    $userScore = max(1, (int) $user->getScore());

    // final risk score
    $riskScore = $baseScore / $userScore;

    $recentRequestCount = $this->getPendingSpamRequestCount($assignmentRepository, $user);
    $isBlocked = (bool) $user->isBlocked();

    if (!$isBlocked && ($riskScore > 50 || $recentRequestCount >= self::SPAM_REQUEST_LIMIT)) {
        $user->setIsBlocked(true);
        $isBlocked = true;
        $em->flush();
    }

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
    ResourceAssignmentRepository $assignmentRepository,
    RequestPdfService $pdfService
): Response {

    $resourceId = $request->request->get('resource_id');
    $projectId = $request->request->get('project_id');
    $quantity = (int)$request->request->get('quantity');

    $resource = $resourceRepository->find($resourceId);
    $project = $projectRepository->find($projectId);

    if (!$resource || !$project || $quantity < 1 || $quantity > $resource->getAvailableQuantity()) {
        $this->addFlash('error', 'Invalid resource or quantity.');
        return $this->redirectToRoute('request_resource');
    }

    // 🔐 USER
    $userId = $this->authService->getCurrentUserId();
    if ($userId === null) {
        throw $this->createAccessDeniedException('You must be logged in.');
    }

    $user = $em->getRepository(Utilisateur::class)->find($userId);
    if (!$user) {
        throw $this->createNotFoundException("User not found.");
    }

    // 🎯 SCORE LOGIC
    $score = $user->getScore();

    if ($user->isBlocked()) {
        $this->addFlash('error', 'Your account is blocked from requesting resources.');
        return $this->redirectToRoute('request_resource');
    }

    $recentRequestCount = $this->getPendingSpamRequestCount($assignmentRepository, $user);
    if (($recentRequestCount + 1) >= self::SPAM_REQUEST_LIMIT) {
        $user->setIsBlocked(true);
        $em->flush();

        $this->addFlash(
            'error',
            'Too many pending resource requests. Your account has been blocked.'
        );

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
    $assignment->setTotalCost(bcmul($resource->getUnitCost(), $quantity, 2));

    // 🔥 IMPORTANT: RETURN DATE = PROJECT END DATE
    $assignment->setReturnDate($project->getEndDate());

    $assignment->setUtilisateur($user);
    $assignment->setPenaltyDaysApplied(0);
    $assignment->setBonusApplied(false);

    $em->persist($assignment);

    // update stock
    

    $em->flush();

    // 📄 PDF
    $pdfData = [
        'user' => $user->getNom() . ' ' . $user->getPrenom(),
        'resource' => $resource->getResourceName(),
        'quantity' => $quantity,
        'cost' => bcmul($resource->getUnitCost(), $quantity, 2),
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

    #[Route('/admin/request/{id}/accept', name: 'accept_request')]
public function accept(
    int $id,
    ResourceAssignmentRepository $repo,
    ResourceRepository $resourceRepository,
    EntityManagerInterface $em,
    MailerInterface $mailer
): Response {

    $assignment = $repo->find($id);

    if (!$assignment || !$assignment->getUtilisateur()) {
        $this->addFlash('error', 'Request not found or client missing.');
        return $this->redirectToRoute('manage_requests');
    }

    $client = $assignment->getUtilisateur();

    // =========================
    // UPDATE STATUS
    // =========================
    $assignment->setStatus('ACCEPTED');

    // =========================
    // STOCK UPDATE (ONLY HERE)
    // =========================
    $resource = $resourceRepository->find($assignment->getResourceId());

    if (!$resource) {
        $this->addFlash('error', 'Resource not found.');
        return $this->redirectToRoute('manage_requests');
    }

    if ($resource->getAvailableQuantity() < $assignment->getQuantity()) {
        $this->addFlash('error', 'Not enough stock available.');
        return $this->redirectToRoute('manage_requests');
    }

    $resource->setAvailableQuantity(
        $resource->getAvailableQuantity() - $assignment->getQuantity()
    );

    // =========================
    // SAVE
    // =========================
    $em->flush();

    // =========================
    // EMAIL NOTIFICATION
    // =========================
    $fromAddress = $_ENV['MAILER_FROM'] ?? 'simawiyass124@gmail.com';

    $email = (new Email())
        ->from($fromAddress)
        ->to($client->getEmail())
        ->subject('Resource Request Accepted - NEXUM')
        ->text($this->buildRequestEmailText($assignment, $client, 'accepted'))
        ->html($this->buildRequestEmailHtml($assignment, $client, 'accepted'));

    try {
        $mailer->send($email);
        $this->addFlash('success', 'Request accepted and client notified.');
    } catch (TransportExceptionInterface $e) {
        $this->addFlash('error', 'Email notification failed: ' . $e->getMessage());
    }

    return $this->redirectToRoute('manage_requests');
}
    #[Route('/admin/request/{id}/decline', name: 'decline_request')]
    public function decline(
        int $id,
        ResourceAssignmentRepository $repo,
        EntityManagerInterface $em,
        ResourceRepository $resourceRepository,
        MailerInterface $mailer
    ): Response {
        $assignment = $repo->find($id);
        if (!$assignment || !$assignment->getUtilisateur()) {
            $this->addFlash('error', 'Request not found or client missing.');
            return $this->redirectToRoute('manage_requests');
        }

        $resource = $resourceRepository->find($assignment->getResourceId());
        if ($resource) {
            $resource->setAvailableQuantity(
                $resource->getAvailableQuantity() + $assignment->getQuantity()
            );
        }

        $assignment->setStatus('DECLINED');
        $em->flush();

        $client = $assignment->getUtilisateur();
        $fromAddress = $_ENV['MAILER_FROM'] ?? 'simawiyass124@gmail.com';
        $email = (new Email())
            ->from($fromAddress)
            ->to($client->getEmail())
            ->subject('Resource Request Update - NEXUM')
            ->text($this->buildRequestEmailText($assignment, $client, 'declined'))
            ->html($this->buildRequestEmailHtml($assignment, $client, 'declined'));

        try {
            $mailer->send($email);
            $this->addFlash('success', 'Client notified by email.');
        } catch (TransportExceptionInterface $e) {
            $this->addFlash('error', 'Email notification failed: '.$e->getMessage());
        }

        return $this->redirectToRoute('manage_requests');
    }
    // --- NEW DELETE route for front page ---
    #[Route('/assignment/{id}/delete', name: 'delete_assignment', methods: ['POST'])]
    public function delete(
        int $id,
        ResourceAssignmentRepository $repo,
        EntityManagerInterface $em,
        ResourceRepository $resourceRepository
    ): Response {
        $assignment = $repo->find($id);
        if ($assignment) {
            $resource = $resourceRepository->find($assignment->getResourceId());
            if ($resource && $assignment->getStatus() !== 'DECLINED') {
                $resource->setAvailableQuantity($resource->getAvailableQuantity() + $assignment->getQuantity());
            }
            $em->remove($assignment);
            $em->flush();
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
    ResourceRepository $resourceRepository
): Response
{
    $assignment = $repo->find($id);
    if (!$assignment) {
        throw $this->createNotFoundException("Assignment with ID $id not found.");
    }

    // CSRF check
    $submittedToken = $request->request->get('_token');
    if (!$this->isCsrfTokenValid('edit'.$id, $submittedToken)) {
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

    // Compute available quantity including the old assigned quantity
    $availableQuantity = $resource->getAvailableQuantity() + $assignment->getQuantity();
    if ($newQuantity < 1 || $newQuantity > $availableQuantity) {
        $this->addFlash('error', 'Quantity must be between 1 and '.$availableQuantity);
        return $this->redirectToRoute('client_resources_view');
    }

    // Update resource available quantity
    $resource->setAvailableQuantity($availableQuantity - $newQuantity);

    // Update assignment
    $assignment->setQuantity($newQuantity);
    $assignment->setReturnDate($newReturnDate ? new \DateTime($newReturnDate) : null);
    $assignment->setStatus('PENDING'); // reset status to PENDING
    // totalCost remains unchanged

    $em->flush();

    $this->addFlash('success', 'Assignment updated successfully.');

    return $this->redirectToRoute('client_resources_view');
}

    private function getPendingSpamRequestCount(
        ResourceAssignmentRepository $assignmentRepository,
        Utilisateur $user
    ): int {
        return $assignmentRepository->countPendingRequestsByUser($user);
    }

    private function buildRequestEmailText(
        ResourceAssignment $assignment,
        Utilisateur $client,
        string $state
    ): string {
        $statusLabel = strtoupper($state);
        $intro = $state === 'accepted'
            ? 'Your resource request has been approved.'
            : 'Your resource request has been declined.';

        return implode("\n", [
            "NEXUM Resource Management",
            "",
            "Hello {$client->getPrenom()} {$client->getNom()},",
            $intro,
            "",
            "Request details:",
            "Resource ID: {$assignment->getResourceId()}",
            "Quantity: {$assignment->getQuantity()}",
            "Status: {$statusLabel}",
            "Request date: {$assignment->getAssignmentDate()?->format('Y-m-d H:i')}",
            "Return date: {$assignment->getReturnDate()?->format('Y-m-d')}",
            "",
            "This is an automated message from NEXUM.",
        ]);
    }

    private function buildRequestEmailHtml(
        ResourceAssignment $assignment,
        Utilisateur $client,
        string $state
    ): string {
        $isAccepted = $state === 'accepted';
        $accent = $isAccepted ? '#12805c' : '#b33a3a';
        $softAccent = $isAccepted ? '#e9f8f2' : '#fff1f1';
        $eyebrow = $isAccepted ? 'Request approved' : 'Request declined';
        $headline = $isAccepted
            ? 'Your resource request has been approved'
            : 'Your resource request could not be approved';
        $summary = $isAccepted
            ? 'Your request is now confirmed and ready for use in the resource management workflow.'
            : 'This request was not approved at this time. You can review the request details below and submit another one if needed.';
        $statusLabel = $isAccepted ? 'ACCEPTED' : 'DECLINED';
        $nextStep = $isAccepted
            ? 'You can now proceed with your assigned resource and coordinate with your team if any follow-up is needed.'
            : 'If this resource is still required, you can submit a new request later or contact the admin team for clarification.';
        $fullName = htmlspecialchars(trim($client->getPrenom() . ' ' . $client->getNom()), ENT_QUOTES, 'UTF-8');
        $resourceId = htmlspecialchars((string) $assignment->getResourceId(), ENT_QUOTES, 'UTF-8');
        $quantity = htmlspecialchars((string) $assignment->getQuantity(), ENT_QUOTES, 'UTF-8');
        $assignmentDate = htmlspecialchars((string) $assignment->getAssignmentDate()?->format('d M Y, H:i'), ENT_QUOTES, 'UTF-8');
        $returnDate = htmlspecialchars((string) ($assignment->getReturnDate()?->format('d M Y') ?? 'Not specified'), ENT_QUOTES, 'UTF-8');

        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NEXUM Resource Update</title>
</head>
<body style="margin:0;padding:0;background-color:#eef3f8;font-family:Segoe UI,Arial,sans-serif;color:#1f2937;">
    <div style="padding:32px 16px;">
        <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="max-width:680px;margin:0 auto;background:#ffffff;border-radius:20px;overflow:hidden;box-shadow:0 18px 45px rgba(15,23,42,0.12);">
            <tr>
                <td style="background:linear-gradient(135deg,#0f172a 0%,#1e3a5f 100%);padding:32px 36px;color:#ffffff;">
                    <div style="font-size:12px;letter-spacing:0.18em;text-transform:uppercase;opacity:0.78;margin-bottom:10px;">NEXUM Resource Management</div>
                    <div style="font-size:30px;font-weight:700;line-height:1.2;margin-bottom:8px;">{$headline}</div>
                    <div style="font-size:15px;line-height:1.7;max-width:520px;color:#dbe7f5;">{$summary}</div>
                </td>
            </tr>
            <tr>
                <td style="padding:32px 36px 12px 36px;">
                    <div style="display:inline-block;background:{$softAccent};color:{$accent};padding:8px 14px;border-radius:999px;font-size:12px;font-weight:700;letter-spacing:0.08em;text-transform:uppercase;">{$eyebrow}</div>
                    <p style="margin:22px 0 10px 0;font-size:16px;line-height:1.7;">Hello {$fullName},</p>
                    <p style="margin:0 0 22px 0;font-size:16px;line-height:1.7;color:#475569;">{$nextStep}</p>
                </td>
            </tr>
            <tr>
                <td style="padding:0 36px 24px 36px;">
                    <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="border:1px solid #dbe5f0;border-radius:16px;overflow:hidden;background:#fbfdff;">
                        <tr>
                            <td colspan="2" style="padding:18px 22px;background:#f6f9fc;font-size:15px;font-weight:700;color:#0f172a;border-bottom:1px solid #dbe5f0;">Request details</td>
                        </tr>
                        <tr>
                            <td style="padding:14px 22px;font-size:14px;color:#64748b;border-bottom:1px solid #e7eef6;">Resource ID</td>
                            <td style="padding:14px 22px;font-size:14px;font-weight:600;color:#0f172a;border-bottom:1px solid #e7eef6;">{$resourceId}</td>
                        </tr>
                        <tr>
                            <td style="padding:14px 22px;font-size:14px;color:#64748b;border-bottom:1px solid #e7eef6;">Quantity</td>
                            <td style="padding:14px 22px;font-size:14px;font-weight:600;color:#0f172a;border-bottom:1px solid #e7eef6;">{$quantity}</td>
                        </tr>
                        <tr>
                            <td style="padding:14px 22px;font-size:14px;color:#64748b;border-bottom:1px solid #e7eef6;">Status</td>
                            <td style="padding:14px 22px;border-bottom:1px solid #e7eef6;">
                                <span style="display:inline-block;background:{$softAccent};color:{$accent};padding:6px 12px;border-radius:999px;font-size:12px;font-weight:700;letter-spacing:0.05em;">{$statusLabel}</span>
                            </td>
                        </tr>
                        <tr>
                            <td style="padding:14px 22px;font-size:14px;color:#64748b;border-bottom:1px solid #e7eef6;">Request date</td>
                            <td style="padding:14px 22px;font-size:14px;font-weight:600;color:#0f172a;border-bottom:1px solid #e7eef6;">{$assignmentDate}</td>
                        </tr>
                        <tr>
                            <td style="padding:14px 22px;font-size:14px;color:#64748b;">Return date</td>
                            <td style="padding:14px 22px;font-size:14px;font-weight:600;color:#0f172a;">{$returnDate}</td>
                        </tr>
                    </table>
                </td>
            </tr>
            <tr>
                <td style="padding:0 36px 30px 36px;">
                    <div style="background:#f8fafc;border-left:4px solid {$accent};padding:18px 18px 18px 20px;border-radius:12px;font-size:14px;line-height:1.7;color:#475569;">
                        This is an automated notification from NEXUM. If you need support or more context about this request, please contact the resource management team.
                    </div>
                </td>
            </tr>
            <tr>
                <td style="padding:20px 36px 30px 36px;background:#f8fafc;border-top:1px solid #e2e8f0;font-size:12px;line-height:1.8;color:#64748b;text-align:center;">
                    <div style="font-weight:700;letter-spacing:0.08em;text-transform:uppercase;color:#334155;">NEXUM</div>
                    <div>Professional resource operations and request tracking</div>
                    <div style="margin-top:6px;">This email was generated automatically. Please do not reply directly to this message.</div>
                </td>
            </tr>
        </table>
    </div>
</body>
</html>
HTML;
    }
}
