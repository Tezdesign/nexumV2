<?php

namespace App\Controller\ResourcesManagement;

use App\Attribute\RequireAdmin;
use App\Repository\ResourcesManagement\ResourceAssignmentRepository;
use App\Service\ResourcesManagement\ResourceForecastService;
use App\Service\ResourcesManagement\ResourceStockService;
use App\Entity\ResourcesManagement\Resource;
use App\Form\ResourcesManagement\ResourceType;
use App\Repository\ResourcesManagement\ResourceRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;


#[Route('/admin/resources')]
#[RequireAdmin]
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

            if ($form->isValid()) {
                // Only a valid form stores the file, so a rejected one leaves nothing in public/uploads.
                $this->storeImage($form->get('image_path')->getData(), $resource);
                $entityManager->persist($resource);
                $entityManager->flush();

                $this->addFlash('success', 'Resource added successfully!');
                return $this->redirectToRoute('app_resource_management_index');
            } else {
                // Collect PHP validation errors
                $errors = [];
                foreach ($form->getErrors(true) as $error) {
                    if ($error instanceof \Symfony\Component\Form\FormError) {
                        $errors[] = $error->getMessage();
                    } else {
                        $errors[] = (string) $error;
                    }
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
    public function edit(Request $request, Resource $resource, EntityManagerInterface $entityManager, ResourceStockService $stock): Response
    {
        $form = $this->createForm(ResourceType::class, $resource);
        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            $held = $stock->heldQuantity($resource);
            if ((int) $resource->getTotalQuantity() < $held) {
                $form->get('total_quantity')->addError(new \Symfony\Component\Form\FormError(
                    sprintf('Total quantity cannot be lower than the %d unit(s) currently lent out.', $held)
                ));
            }

            if ($form->isValid()) {
                $this->storeImage($form->get('image_path')->getData(), $resource);

                // Available stock = total minus what is lent out, not the new total.
                $stock->recalculate($resource);

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
        if ($this->isCsrfTokenValid('delete' . $resource->getResourceId(), (string) $request->request->get('_token'))) {
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
    ResourceRepository $resourceRepository,
    EntityManagerInterface $em,
    ResourceStockService $stock
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

    // 🔥 SCORE CALCULATION (only if user and return date exist)
    if ($user instanceof \App\Entity\UserHandling\Utilisateur && $returnDate instanceof \DateTimeInterface) {
        if ($today < $returnDate) {
            $user->setScore($user->getScore() + 20);
        } elseif ($today->format('Y-m-d') === $returnDate->format('Y-m-d')) {
            $user->setScore($user->getScore() + 10);
        } else {
            $diff = $today->diff($returnDate)->days;
            $penalty = $diff * 10;

            $newScore = $user->getScore() - $penalty;

            // prevent negative score
            if ($newScore < 0) {
                $newScore = 0;
            }

            $user->setScore($newScore);
        }
    }

    // ✅ mark returned and put the units back in stock
    $resource = $resourceRepository->find($assignment->getResourceId());
    $em->wrapInTransaction(function () use ($em, $assignment, $resource, $stock): void {
        if ($resource) {
            $stock->lock($resource);
        }
        $assignment->setReturned(true);
        $em->flush();
        if ($resource) {
            $stock->recalculate($resource);
            $em->flush();
        }
    });

    $this->addFlash('success', 'Resource marked as returned and score updated.');

    return $this->redirectToRoute('admin_active_returns');
}
#[Route('/admin/calendar', name: 'admin_calendar')]
public function calendar(ResourceAssignmentRepository $repo): Response
{
    $assignments = $repo->findAll();

    $events = [];
    $dayData = [];

    $today = new \DateTimeImmutable('today');

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
        $status = $this->returnStatus($a, $today);
        $isOverdue = $status === 'OVERDUE';
        $color = match ($status) {
            'OVERDUE' => '#dc3545',
            'DUE TODAY' => '#fd7e14',
            'RETURNED' => '#6c757d',
            default => '#28a745',
        };

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

        $today = new \DateTimeImmutable('today');

        // 🎯 COLOR LOGIC
        $status = $this->returnStatus($a, $today);
        $returnDateObj = $a->getReturnDate();
        $dueTomorrow = $status === 'ACTIVE' && $returnDateObj instanceof \DateTimeInterface
            && $returnDateObj->format('Y-m-d') === $today->modify('+1 day')->format('Y-m-d');
        $color = match (true) {
            $status === 'OVERDUE' => '#dc3545', // red (late)
            $status === 'DUE TODAY', $dueTomorrow => '#fd7e14', // orange (today or next day)
            $status === 'RETURNED' => '#6c757d', // grey
            default => '#28a745', // green
        };

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
#[Route('/predict', name: 'resource_predict', methods: ['GET'])]
public function predict(ResourceForecastService $forecast): Response
{
    $prediction = $forecast->forecast($forecast->dataset());

    return new Response($prediction === null ? 'Forecast unavailable.' : (string) $prediction, $prediction === null ? 503 : 200, [
        'Content-Type' => 'text/plain'
    ]);
}
#[Route('/prediction', name: 'resource_prediction_page')]
public function predictionPage(
    ResourceRepository $resourceRepository,
    ResourceForecastService $forecast
): Response
{
    $resources = array_map(
        static fn (Resource $resource): array => [
            'id' => $resource->getResourceId(),
            'name' => $resource->getResourceName(),
            'type' => $resource->getResourceType(),
            'total' => $resource->getTotalQuantity(),
            'available' => $resource->getAvailableQuantity(),
        ],
        $resourceRepository->findAll()
    );

    $data = $forecast->dataset();
    $prediction = $forecast->forecast($data);

    return $this->render('resources-management/prediction.html.twig', [
        'prediction' => $prediction ?? 0.0,
        'forecastFailed' => $prediction === null,
        'data' => $data,
        'resources' => $resources,
    ]);
}

private function storeImage(mixed $imageFile, Resource $resource): void
{
    if (!$imageFile instanceof UploadedFile) {
        return;
    }

    $projectDir = $this->getParameter('kernel.project_dir');
    if (!is_string($projectDir)) {
        throw new \RuntimeException('Invalid project directory parameter.');
    }

    // The form already limited the type to jpeg, png, gif or webp, so the guessed extension is one of those.
    $newFilename = bin2hex(random_bytes(8)) . '.' . ($imageFile->guessExtension() ?? 'img');
    $imageFile->move($projectDir . '/public/uploads', $newFilename);
    $resource->setImagePath('uploads/' . $newFilename);
}

/**
 * RETURNED, OVERDUE, DUE TODAY or ACTIVE. Dates are compared by day: a return date is a date without a
 * time, so comparing it to the current time would call an item overdue from midnight on its due day.
 */
private function returnStatus(\App\Entity\ResourcesManagement\ResourceAssignment $assignment, \DateTimeImmutable $today): string
{
    $returnDate = $assignment->getReturnDate();

    if ($assignment->isReturned()) {
        return 'RETURNED';
    }
    if (!$returnDate instanceof \DateTimeInterface) {
        return 'ACTIVE';
    }

    $due = $returnDate->format('Y-m-d');

    return match (true) {
        $due < $today->format('Y-m-d') => 'OVERDUE',
        $due === $today->format('Y-m-d') => 'DUE TODAY',
        default => 'ACTIVE',
    };
}

}
