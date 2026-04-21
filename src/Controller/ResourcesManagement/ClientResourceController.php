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
        EntityManagerInterface $em
    ): Response {
        $resources = $resourceRepository->createQueryBuilder('r')
            ->where('r.available_quantity > 0')
            ->getQuery()
            ->getResult();

        $userId = $this->authService->getCurrentUserId();
        if ($userId === null) {
            throw $this->createAccessDeniedException('You must be logged in to request a resource.');
        }

        $user = $em->getRepository(Utilisateur::class)->find($userId);
        if (!$user) {
            throw $this->createNotFoundException("User with ID $userId not found.");
        }

        $projects = $projectRepository->findBy(['assigned_to' => $user->getId()]);

        return $this->render('resources-management/request-resource.html.twig', [
            'resources' => $resources,
            'projects' => $projects,
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
    $resource->setAvailableQuantity(
        $resource->getAvailableQuantity() - $quantity
    );

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
        EntityManagerInterface $em,
        MailerInterface $mailer
    ): Response {
        $assignment = $repo->find($id);
        if (!$assignment || !$assignment->getUtilisateur()) {
            $this->addFlash('error', 'Request not found or client missing.');
            return $this->redirectToRoute('manage_requests');
        }

        $client = $assignment->getUtilisateur();
        $assignment->setStatus('ACCEPTED');
        $em->flush();

        $fromAddress = $_ENV['MAILER_FROM'] ?? 'simawiyass124@gmail.com';
        $email = (new Email())
            ->from($fromAddress)
            ->to($client->getEmail())
            ->subject('Resource Request Accepted - NEXUM')
            ->text("Your request has been accepted.\nResource ID: {$assignment->getResourceId()}\nQuantity: {$assignment->getQuantity()}")
            ->html("
                <!DOCTYPE html>
                <html lang='en'>
                <head>
                    <meta charset='UTF-8'>
                    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
                    <title>Resource Request Accepted</title>
                    <style>
                        body { font-family: Arial, sans-serif; margin: 0; padding: 0; background-color: #f4f4f4; }
                        .container { max-width: 600px; margin: 0 auto; background-color: #ffffff; }
                        .header { background-color: #007bff; color: #ffffff; padding: 20px; text-align: center; }
                        .header h1 { margin: 0; font-size: 24px; }
                        .content { padding: 30px; }
                        .content h2 { color: #333333; }
                        .details { background-color: #f8f9fa; padding: 20px; border-radius: 5px; margin: 20px 0; }
                        .footer { background-color: #343a40; color: #ffffff; padding: 20px; text-align: center; font-size: 12px; }
                        .button { display: inline-block; padding: 10px 20px; background-color: #28a745; color: #ffffff; text-decoration: none; border-radius: 5px; margin-top: 20px; }
                    </style>
                </head>
                <body>
                    <div class='container'>
                        <div class='header'>
                            <h1>NEXUM</h1>
                            <p>Resource Management System</p>
                        </div>
                        <div class='content'>
                            <h2>Great News! Your Resource Request Has Been Accepted</h2>
                            <p>Dear {$client->getNom()} {$client->getPrenom()},</p>
                            <p>We are pleased to inform you that your resource request has been approved and processed successfully.</p>
                            <div class='details'>
                                <h3>Request Details:</h3>
                                <p><strong>Resource ID:</strong> {$assignment->getResourceId()}</p>
                                <p><strong>Quantity:</strong> {$assignment->getQuantity()}</p>
                                <p><strong>Status:</strong> ACCEPTED</p>
                                <p><strong>Request Date:</strong> {$assignment->getAssignmentDate()->format('Y-m-d H:i:s')}</p>
                            </div>
                            <p>You can now access and utilize the allocated resources. If you have any questions or need assistance, please don't hesitate to contact our support team.</p>
                            <a href='#' class='button'>View Your Resources</a>
                        </div>
                        <div class='footer'>
                            <p>&copy; 2026 NEXUM. All rights reserved.</p>
                            <p>This is an automated message. Please do not reply to this email.</p>
                        </div>
                    </div>
                </body>
                </html>
            ");

        try {
            $mailer->send($email);
            $this->addFlash('success', 'Client notified by email.');
        } catch (TransportExceptionInterface $e) {
            $this->addFlash('error', 'Email notification failed: '.$e->getMessage());
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
            ->text("Your request has been declined.\nResource ID: {$assignment->getResourceId()}\nQuantity: {$assignment->getQuantity()}")
            ->html("
                <!DOCTYPE html>
                <html lang='en'>
                <head>
                    <meta charset='UTF-8'>
                    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
                    <title>Resource Request Declined</title>
                    <style>
                        body { font-family: Arial, sans-serif; margin: 0; padding: 0; background-color: #f4f4f4; }
                        .container { max-width: 600px; margin: 0 auto; background-color: #ffffff; }
                        .header { background-color: #007bff; color: #ffffff; padding: 20px; text-align: center; }
                        .header h1 { margin: 0; font-size: 24px; }
                        .content { padding: 30px; }
                        .content h2 { color: #333333; }
                        .details { background-color: #f8f9fa; padding: 20px; border-radius: 5px; margin: 20px 0; }
                        .footer { background-color: #343a40; color: #ffffff; padding: 20px; text-align: center; font-size: 12px; }
                        .button { display: inline-block; padding: 10px 20px; background-color: #dc3545; color: #ffffff; text-decoration: none; border-radius: 5px; margin-top: 20px; }
                    </style>
                </head>
                <body>
                    <div class='container'>
                        <div class='header'>
                            <h1>NEXUM</h1>
                            <p>Resource Management System</p>
                        </div>
                        <div class='content'>
                            <h2>Resource Request Update</h2>
                            <p>Dear {$client->getNom()} {$client->getPrenom()},</p>
                            <p>We regret to inform you that your resource request has been declined at this time.</p>
                            <div class='details'>
                                <h3>Request Details:</h3>
                                <p><strong>Resource ID:</strong> {$assignment->getResourceId()}</p>
                                <p><strong>Quantity:</strong> {$assignment->getQuantity()}</p>
                                <p><strong>Status:</strong> DECLINED</p>
                                <p><strong>Request Date:</strong> {$assignment->getAssignmentDate()->format('Y-m-d H:i:s')}</p>
                            </div>
                            <p>This decision may be due to resource availability or other operational considerations. You are welcome to submit a new request or contact our team for more information.</p>
                            <a href='#' class='button'>Submit New Request</a>
                        </div>
                        <div class='footer'>
                            <p>&copy; 2026 NEXUM. All rights reserved.</p>
                            <p>This is an automated message. Please do not reply to this email.</p>
                        </div>
                    </div>
                </body>
                </html>
            ");

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
}