<?php

namespace App\Controller\ResourcesManagement;

use App\Repository\ResourcesManagement\ResourceAssignmentRepository;
use App\Entity\ResourcesManagement\Resource;
use App\Form\ResourcesManagement\ResourceType;
use App\Repository\ResourcesManagement\ResourceRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;


#[Route('/admin/resources')]
final class ResourceController extends AbstractController
{
    /**
     * List all resources
     */
    #[Route('/', name: 'app_resource_management_index', methods: ['GET'])]
    public function index(ResourceRepository $resourceRepository): Response
    {
        $resources = $resourceRepository->findAll();

        return $this->render('resources-management/apps-resources-management.html.twig', [
            'resources' => $resources,
        ]);
    }

    /**
     * Create a new resource
     */
    #[Route('/add', name: 'app_resource_management_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $resource = new Resource();
        $form = $this->createForm(ResourceType::class, $resource);
        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            // Set default fields
            $resource->setStatus('AVAILABLE');
            $resource->setAvailableQuantity($resource->getTotalQuantity() ?? 0);

            // Handle image upload
            $imageFile = $form->get('image_path')->getData();
            if ($imageFile) {
                $newFilename = uniqid() . '.' . $imageFile->guessExtension();
                $imageFile->move(
                    $this->getParameter('kernel.project_dir') . '/public/uploads',
                    $newFilename
                );
                $resource->setImagePath('uploads/' . $newFilename);
            }

            if ($form->isValid()) {
                $entityManager->persist($resource);
                $entityManager->flush();

                $this->addFlash('success', 'Resource added successfully!');
                return $this->redirectToRoute('app_resource_management_index');
            } else {
                // Collect PHP validation errors
                $errors = [];
                foreach ($form->getErrors(true) as $error) {
                    $errors[] = $error->getMessage();
                }
                $this->addFlash('danger', implode('<br>', $errors));
            }
        }

        return $this->render('resources-management/apps-resources-add.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    /**
     * Edit an existing resource
     */
    #[Route('/{resource_id}/edit', name: 'app_resources_management_resource_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Resource $resource, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(ResourceType::class, $resource);
        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            // Handle image upload
            $imageFile = $form->get('image_path')->getData();
            if ($imageFile) {
                $newFilename = uniqid() . '.' . $imageFile->guessExtension();
                $imageFile->move(
                    $this->getParameter('kernel.project_dir') . '/public/uploads',
                    $newFilename
                );
                $resource->setImagePath('uploads/' . $newFilename);
            }

            if ($form->isValid()) {
                // Update available quantity based on total_quantity
                $resource->setAvailableQuantity($resource->getTotalQuantity() ?? $resource->getAvailableQuantity());

                $entityManager->flush();

                $this->addFlash('success', 'Resource updated successfully!');
                return $this->redirectToRoute('app_resource_management_index');
            } else {
                // Collect PHP validation errors
                $errors = [];
                foreach ($form->getErrors(true) as $error) {
                    $errors[] = $error->getMessage();
                }
                $this->addFlash('danger', implode('<br>', $errors));
            }
        }

        return $this->render('resources-management/apps-resources-add.html.twig', [
            'form' => $form->createView(),
            'resource' => $resource,
            'is_edit' => true,
        ]);
    }

    /**
     * Delete a resource
     */
    #[Route('/{resource_id}', name: 'app_resources_management_resource_delete', methods: ['POST'])]
    public function delete(Request $request, Resource $resource, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete' . $resource->getResourceId(), $request->request->get('_token'))) {
            $entityManager->remove($resource);
            $entityManager->flush();
            $this->addFlash('success', 'Resource deleted successfully!');
        }

        return $this->redirectToRoute('app_resource_management_index');
    }
    #[Route('/admin/returns', name: 'admin_active_returns')]
public function activeReturns(
    ResourceAssignmentRepository $repo,
    ResourceRepository $resourceRepository
): Response {

    $assignments = $repo->createQueryBuilder('a')
        ->where('a.status = :status')
        ->andWhere('a.returned = false')
        ->setParameter('status', 'ACCEPTED')
        ->getQuery()
        ->getResult();

    $data = [];

    foreach ($assignments as $assignment) {
        $resource = $resourceRepository->find($assignment->getResourceId());

        // 🔥 ONLY PHYSICAL
        if ($resource && $resource->getResourceType() === 'PHYSICAL') {
            $data[] = [
                'assignment' => $assignment,
                'resource' => $resource
            ];
        }
    }

    return $this->render('resources-management/admin-returns.html.twig', [
        'data' => $data
    ]);
}
#[Route('/admin/return/{id}', name: 'mark_returned')]
public function markReturned(
    int $id,
    ResourceAssignmentRepository $repo,
    EntityManagerInterface $em
): Response {

    $assignment = $repo->find($id);

    if (!$assignment) {
        throw $this->createNotFoundException('Assignment not found.');
    }

    if ($assignment->isReturned()) {
        $this->addFlash('info', 'Already returned.');
        return $this->redirectToRoute('admin_active_returns');
    }

    $user = $assignment->getUtilisateur();

    $today = new \DateTime();
    $returnDate = $assignment->getReturnDate();

    // 🔥 SCORE CALCULATION
    if ($today < $returnDate) {
        $user->setScore($user->getScore() + 20);
    } 
    elseif ($today->format('Y-m-d') === $returnDate->format('Y-m-d')) {
        $user->setScore($user->getScore() + 10);
    } 
    else {
        $diff = $today->diff($returnDate)->days;
        $penalty = $diff * 10;

        $newScore = $user->getScore() - $penalty;

        // prevent negative score
        if ($newScore < 0) {
            $newScore = 0;
        }

        $user->setScore($newScore);
    }

    // ✅ mark returned
    $assignment->setReturned(true);

    $em->flush();

    $this->addFlash('success', 'Resource marked as returned and score updated.');

    return $this->redirectToRoute('admin_active_returns');
}
#[Route('/admin/calendar', name: 'admin_calendar')]
public function calendar(ResourceAssignmentRepository $repo): Response
{
    $assignments = $repo->findAll();

    $events = [];
    $dayData = [];

    $today = new \DateTime();

    foreach ($assignments as $a) {

        if (!$a->getAssignmentDate() || !$a->getReturnDate()) {
            continue;
        }

        $start = $a->getAssignmentDate();
        $end = $a->getReturnDate();

        $id = $a->getAssignment_id();
        $resourceId = $a->getResourceId();

        // =========================
        // 📌 START DAY DATA
        // =========================
        $dayData[$start->format('Y-m-d')][] = [
            'assignmentId' => $id,
            'resource' => $resourceId,
            'quantity' => $a->getQuantity(),
            'status' => 'STARTED',
            'returnDate' => $end->format('Y-m-d'),
        ];

        // =========================
        // 📌 RETURN DAY DATA
        // =========================
        $isOverdue = $end < $today;
        $isToday = $end->format('Y-m-d') === $today->format('Y-m-d');

        if ($isOverdue) {
            $status = 'OVERDUE';
            $color = '#dc3545';
        } elseif ($isToday) {
            $status = 'DUE TODAY';
            $color = '#fd7e14';
        } else {
            $status = 'ACTIVE';
            $color = '#28a745';
        }

        $dayData[$end->format('Y-m-d')][] = [
            'assignmentId' => $id,
            'resource' => $resourceId,
            'quantity' => $a->getQuantity(),
            'status' => $status,
            'returnDate' => $end->format('Y-m-d'),
            'overdue' => $isOverdue
        ];

        // =========================
        // 📌 CALENDAR EVENTS
        // =========================

        // START EVENT (blue)
        $events[] = [
            'id' => $id,
            'title' => "Start #$resourceId",
            'start' => $start->format('Y-m-d'),
            'color' => '#0d6efd'
        ];

        // RETURN EVENT (status color)
        $events[] = [
            'id' => $id,
            'title' => "$status #$resourceId",
            'start' => $end->format('Y-m-d'),
            'color' => $color,
            'extendedProps' => [
                'assignmentId' => $id,
                'resourceId' => $resourceId,
                'status' => $status,
                'overdue' => $isOverdue
            ]
        ];
    }

    return $this->render('resources-management/calendar.html.twig', [
        'events' => $events,
        'dayData' => $dayData
    ]);
}
#[Route('/admin/calendar/events', name: 'admin_calendar_events')]
public function calendarEvents(ResourceAssignmentRepository $repo): Response
{
    $assignments = $repo->findAll();

    $events = [];

    foreach ($assignments as $a) {

        $start = $a->getAssignmentDate()?->format('Y-m-d');
        $end = $a->getReturnDate()?->format('Y-m-d');

        $today = new \DateTime();

        // 🎯 COLOR LOGIC
        $color = '#28a745'; // green default

        if ($a->getReturnDate() < $today) {
            $color = '#dc3545'; // red (late)
        } elseif ($a->getReturnDate()->format('Y-m-d') === $today->format('Y-m-d') ||
                  $a->getReturnDate()->diff($today)->days == 1) {
            $color = '#fd7e14'; // orange (today or next day)
        }

        $events[] = [
            'title' => 'Resource #' . $a->getResourceId(),
            'start' => $start,
            'end' => $end,
            'color' => $color,
            'extendedProps' => [
                'status' => $a->getStatus(),
                'quantity' => $a->getQuantity()
            ]
        ];
    }

    return $this->json($events);
}

}