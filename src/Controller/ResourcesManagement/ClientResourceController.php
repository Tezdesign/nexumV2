<?php

namespace App\Controller\ResourcesManagement;

use App\Entity\ResourcesManagement\ResourceAssignment;
use App\Entity\ResourcesManagement\Resource;
use App\Repository\ResourcesManagement\ResourceAssignmentRepository;
use App\Repository\ResourcesManagement\ResourceRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/client/resources')]
class ClientResourceController extends AbstractController
{
    /**
     * Show resource assignments for a specific client (id=53 for now)
     */
    #[Route('/53', name: 'client_resources_view', methods: ['GET'])]
    public function view(
        ResourceAssignmentRepository $assignmentRepository,
        ResourceRepository $resourceRepository
    ): Response
    {
        $clientId = 53;

        // Get all assignments for this client
        $assignments = $assignmentRepository->findBy([
            'client_code' => $clientId
        ]);

        // Collect resources info
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
            'resourceData' => $resourceData
        ]);
    }
}