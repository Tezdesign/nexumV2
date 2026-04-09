<?php

namespace App\Controller\ResourcesManagement;

use App\Entity\ResourcesManagement\ResourceAssignment;
use App\Entity\ResourcesManagement\Resource;
use App\Entity\UserHandling\Utilisateur;
use App\Repository\ResourcesManagement\ResourceAssignmentRepository;
use App\Repository\ResourcesManagement\ResourceRepository;
use App\Repository\Projects\ProjectRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/client/resources')]
class ClientResourceController extends AbstractController
{
    #[Route('/53', name: 'client_resources_view', methods: ['GET'])]
    public function view(
        ResourceAssignmentRepository $assignmentRepository,
        ResourceRepository $resourceRepository,
        EntityManagerInterface $em
    ): Response
    {
        $userId = 53;
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

        $user = $this->getUser() ?? $em->getRepository(Utilisateur::class)->find(53);
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
        ProjectRepository $projectRepository
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

        $assignment = new ResourceAssignment();
        $assignment->setResourceId($resourceId);
        $assignment->setProjectCode((string)$project->getId());
        $assignment->setQuantity($quantity);
        $assignment->setAssignmentDate(new \DateTime());
        $assignment->setStatus('PENDING');
        $assignment->setTotalCost(bcmul($resource->getUnitCost(), $quantity, 2));
        $assignment->setUtilisateur($this->getUser() ?? $em->getRepository(Utilisateur::class)->find(53));
        $assignment->setPenaltyDaysApplied(0);
        $assignment->setBonusApplied(false);

        $em->persist($assignment);

        $resource->setAvailableQuantity($resource->getAvailableQuantity() - $quantity);

        $em->flush();

        $this->addFlash('success', 'Resource request submitted successfully.');

        return $this->redirectToRoute('request_resource');
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
        EntityManagerInterface $em
    ): Response {
        $assignment = $repo->find($id);
        if ($assignment) {
            $assignment->setStatus('ACCEPTED');
            $em->flush();
        }
        return $this->redirectToRoute('manage_requests');
    }   

    #[Route('/admin/request/{id}/decline', name: 'decline_request')]
    public function decline(
        int $id,
        ResourceAssignmentRepository $repo,
        EntityManagerInterface $em,
        ResourceRepository $resourceRepository
    ): Response {
        $assignment = $repo->find($id);
        if ($assignment) {
            $resource = $resourceRepository->find($assignment->getResourceId());
            if ($resource) {
                $resource->setAvailableQuantity($resource->getAvailableQuantity() + $assignment->getQuantity());
            }
            $assignment->setStatus('DECLINED');
            $em->flush();
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