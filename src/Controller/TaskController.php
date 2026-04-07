<?php

namespace App\Controller;

use App\Entity\Tasks\Task;
use App\Form\Tasks\TaskManagerUpdateType;
use App\Form\Tasks\TaskQuickCreateType;
use App\Form\Tasks\TaskType;
use App\Form\Tasks\TaskUpdateType;
use App\Repository\Projects\ProjectAssignmentRepository;
use App\Repository\Projects\ProjectRepository;
use App\Repository\Tasks\TaskRepository;
use App\Repository\UserHandling\UtilisateurRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/task')]
final class TaskController extends AbstractController
{
    #[Route(name: 'app_task_index', methods: ['GET', 'POST'])]
    public function index(
        Request $request,
        TaskRepository $taskRepository,
        ProjectRepository $projectRepository,
        ProjectAssignmentRepository $projectAssignmentRepository,
        UtilisateurRepository $utilisateurRepository,
        EntityManagerInterface $entityManager
    ): Response
    {
        $q = trim((string) $request->query->get('q', ''));
        $statusFilter = strtolower(trim((string) $request->query->get('status', '')));
        $priorityFilter = strtolower(trim((string) $request->query->get('priority', '')));
        $statusFilter = in_array($statusFilter, ['todo', 'in_progress', 'done'], true) ? $statusFilter : '';
        $priorityFilter = in_array($priorityFilter, ['high', 'medium', 'low'], true) ? $priorityFilter : '';

        $currentUser = $utilisateurRepository->findFirstManagerOrFirst();
        $currentUserId = $currentUser?->getId() ?? 1;
        $role = strtolower((string) ($currentUser?->getRole() ?? ''));
        $isManager = $role !== '' && str_contains($role, 'manager');

        $prefillProjectId = (int) $request->query->get('project', 0);
        $openCreate = (string) $request->query->get('create', '') === '1';
        $prefillProject = $prefillProjectId > 0 ? $projectRepository->find($prefillProjectId) : null;

        // Modal "quick create" form.
        $createTask = new Task();
        $createForm = $this->createForm(TaskQuickCreateType::class, $createTask, [
            'action' => $this->generateUrl('app_task_index'),
            'method' => 'POST',
        ]);

        // Preselect project when arriving from a per-project "+" button.
        if ($prefillProject !== null) {
            $createForm->get('project')->setData($prefillProject);
        }

        // Default assignee to the current user (manager can change it).
        if ($currentUser !== null) {
            $createForm->get('assignedUser')->setData($currentUser);
        }
        $createForm->handleRequest($request);

        if ($createForm->isSubmitted() && $createForm->isValid()) {
            if (!$isManager) {
                throw $this->createAccessDeniedException();
            }

            /** @var \App\Entity\Projects\Project|null $project */
            $project = $createForm->get('project')->getData();
            if ($project === null || $project->getId() === null) {
                // Shouldn't happen because "project" is required, but stay defensive.
                return $this->redirectToRoute('app_task_index', [], Response::HTTP_SEE_OTHER);
            }

            /** @var \App\Entity\UserHandling\Utilisateur|null $assignedUser */
            $assignedUser = $createForm->get('assignedUser')->getData();
            $assignedUserId = $assignedUser?->getId() ?? null;

            // Only allow assigning to project team members.
            $pid = (int) $project->getId();
            $memberIds = [];
            $createdBy = $project->getCreatedBy();
            if ($createdBy !== null) {
                $memberIds[(int) $createdBy] = true;
            }
            $assignedTo = $project->getAssignedTo();
            if ($assignedTo !== null) {
                $memberIds[(int) $assignedTo] = true;
            }
            foreach ($projectAssignmentRepository->getUserIdsByProjectId($pid) as $uid) {
                $memberIds[(int) $uid] = true;
            }

            if ($assignedUserId !== null && !isset($memberIds[(int) $assignedUserId])) {
                $createForm->get('assignedUser')->addError(new FormError('You can only assign tasks to this project team members.'));
            }

            if (!$createForm->isValid()) {
                // Fall through to render the page with form errors.
            } else {
            $createTask->setProjectId((int) $project->getId());
            $createTask->setAssignedTo($assignedUserId !== null ? (int) $assignedUserId : (int) $currentUserId);
            $createTask->setCreatedBy((int) $currentUserId);
            $createTask->setCreatedAt(new \DateTime());

            $entityManager->persist($createTask);
            $entityManager->flush();

            return $this->redirectToRoute('app_task_index', [], Response::HTTP_SEE_OTHER);
            }
        }

        $tasks = $isManager
            ? $taskRepository->findForManager($q !== '' ? $q : null, $statusFilter !== '' ? $statusFilter : null, $priorityFilter !== '' ? $priorityFilter : null)
            : $taskRepository->findForUser((int) $currentUserId, $q !== '' ? $q : null, $statusFilter !== '' ? $statusFilter : null, $priorityFilter !== '' ? $priorityFilter : null);

        // If a project id is provided, show the board scoped to that project.
        if ($prefillProjectId > 0) {
            $tasks = array_values(array_filter(
                $tasks,
                static fn (Task $t): bool => (int) ($t->getProjectId() ?? 0) === $prefillProjectId
            ));
        }

        $projectIds = [];
        $userIds = [];
        foreach ($tasks as $t) {
            $pid = $t->getProjectId();
            if ($pid !== null) {
                $projectIds[$pid] = true;
            }

            $au = $t->getAssignedTo();
            if ($au !== null) {
                $userIds[$au] = true;
            }
        }

        $projectsById = $projectRepository->findIndexedByIds(array_keys($projectIds));
        $usersById = $utilisateurRepository->findIndexedByIds(array_keys($userIds));
 
        // Hide orphan tasks (tasks whose project was deleted without cascading).
        $tasks = array_values(array_filter(
            $tasks,
            static fn (Task $t): bool => isset($projectsById[(int) ($t->getProjectId() ?? 0)])
        ));

        $avatarUrlById = [];
        foreach ($usersById as $uid => $u) {
            $raw = $u->getImagelink();
            if (is_string($raw)) {
                $raw = trim($raw);
                if ($raw !== '' && preg_match('~^(https?://|/|data:image/)~', $raw) === 1) {
                    $avatarUrlById[(int) $uid] = $raw;
                }
            }
        }

        // Group tasks by project.
        $tasksByProjectId = [];
        foreach ($tasks as $t) {
            $pid = $t->getProjectId() ?? 0;
            $tasksByProjectId[$pid] ??= [];
            $tasksByProjectId[$pid][] = $t;
        }

        return $this->render('task/index.html.twig', [
            'q' => $q,
            'statusFilter' => $statusFilter,
            'priorityFilter' => $priorityFilter,
            'isManager' => $isManager,
            'currentUserId' => (int) $currentUserId,
            'openCreate' => $openCreate,
            'prefillProjectId' => $prefillProjectId,
            'tasksByProjectId' => $tasksByProjectId,
            'projectsById' => $projectsById,
            'usersById' => $usersById,
            'avatarUrlById' => $avatarUrlById,
            'createForm' => $createForm->createView(),
        ]);
    }

    #[Route('/assignees', name: 'app_task_assignees', methods: ['GET'])]
    public function assignees(
        Request $request,
        ProjectRepository $projectRepository,
        ProjectAssignmentRepository $projectAssignmentRepository,
        UtilisateurRepository $utilisateurRepository
    ): JsonResponse
    {
        $pid = (int) $request->query->get('project', 0);
        if ($pid <= 0) {
            return $this->json(['users' => []]);
        }

        $project = $projectRepository->find($pid);
        if ($project === null) {
            return $this->json(['users' => []]);
        }

        $memberIds = [];
        $createdBy = $project->getCreatedBy();
        if ($createdBy !== null) {
            $memberIds[(int) $createdBy] = true;
        }
        $assignedTo = $project->getAssignedTo();
        if ($assignedTo !== null) {
            $memberIds[(int) $assignedTo] = true;
        }
        foreach ($projectAssignmentRepository->getUserIdsByProjectId($pid) as $uid) {
            $memberIds[(int) $uid] = true;
        }

        $usersById = $utilisateurRepository->findIndexedByIds(array_keys($memberIds));
        $users = [];
        foreach ($usersById as $u) {
            $id = $u->getId();
            if ($id === null) {
                continue;
            }
            $name = trim(((string) $u->getPrenom()) . ' ' . ((string) $u->getNom()));
            $users[] = [
                'id' => (int) $id,
                'name' => $name !== '' ? $name : ('User #' . $id),
            ];
        }

        usort($users, static fn (array $a, array $b): int => strcmp((string) $a['name'], (string) $b['name']));

        return $this->json(['users' => $users]);
    }

    #[Route('/new', name: 'app_task_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $task = new Task();
        // Keep the legacy CRUD route around; UI uses the modal on /task.
        $form = $this->createForm(TaskType::class, $task);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($task);
            $entityManager->flush();

            return $this->redirectToRoute('app_task_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('task/new.html.twig', [
            'task' => $task,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_task_show', methods: ['GET'])]
    public function show(Task $task): Response
    {
        return $this->render('task/show.html.twig', [
            'task' => $task,
        ]);
    }

    #[Route('/{id}/status', name: 'app_task_status', methods: ['POST'])]
    public function status(Request $request, Task $task, UtilisateurRepository $utilisateurRepository, EntityManagerInterface $entityManager): Response
    {
        $currentUser = $utilisateurRepository->findFirstManagerOrFirst();
        $currentUserId = (int) ($currentUser?->getId() ?? 0);
        $role = strtolower((string) ($currentUser?->getRole() ?? ''));
        $isManager = $role !== '' && str_contains($role, 'manager');
        if ($isManager) {
            throw $this->createAccessDeniedException();
        }

        if ($task->getAssignedTo() === null || (int) $task->getAssignedTo() !== $currentUserId) {
            throw $this->createAccessDeniedException();
        }

        $token = (string) $request->request->get('_token', '');
        if (!$this->isCsrfTokenValid('task_status'.$task->getId(), $token)) {
            throw $this->createAccessDeniedException();
        }

        $status = strtolower(trim((string) $request->request->get('status', '')));
        $allowed = ['todo', 'in_progress', 'done'];
        if (!in_array($status, $allowed, true)) {
            throw $this->createNotFoundException();
        }

        $task->setStatus($status);
        $task->setUpdatedAt(new \DateTime());
        $entityManager->flush();

        // Prefer returning the user to where they clicked from (internal paths only).
        $back = (string) $request->request->get('back', '');
        if ($back !== '' && str_starts_with($back, '/')) {
            return $this->redirect($back);
        }

        $projectId = $task->getProjectId();
        if ($projectId !== null) {
            return $this->redirectToRoute('app_project_show', ['id' => (int) $projectId, 'tab' => 'tasks']);
        }

        return $this->redirectToRoute('app_task_index');
    }

    #[Route('/{id}/edit', name: 'app_task_edit', methods: ['GET', 'POST'])]
    public function edit(
        Request $request,
        Task $task,
        ProjectRepository $projectRepository,
        ProjectAssignmentRepository $projectAssignmentRepository,
        UtilisateurRepository $utilisateurRepository,
        EntityManagerInterface $entityManager
    ): Response
    {
        $currentUser = $utilisateurRepository->findFirstManagerOrFirst();
        $role = strtolower((string) ($currentUser?->getRole() ?? ''));
        $isManager = $role !== '' && str_contains($role, 'manager');
        if (!$isManager) {
            throw $this->createAccessDeniedException();
        }

        $back = (string) $request->query->get('back', '');
        $back = ($back !== '' && str_starts_with($back, '/')) ? $back : '';

        $memberIds = [];
        $pid = (int) ($task->getProjectId() ?? 0);
        if ($pid > 0) {
            $project = $projectRepository->find($pid);
            if ($project !== null) {
                $createdBy = $project->getCreatedBy();
                if ($createdBy !== null) {
                    $memberIds[(int) $createdBy] = true;
                }
                $assignedTo = $project->getAssignedTo();
                if ($assignedTo !== null) {
                    $memberIds[(int) $assignedTo] = true;
                }
            }
            foreach ($projectAssignmentRepository->getUserIdsByProjectId($pid) as $uid) {
                $memberIds[(int) $uid] = true;
            }
        }

        $form = $this->createForm(TaskManagerUpdateType::class, $task, [
            'member_ids' => array_keys($memberIds),
        ]);

        $assignedId = $task->getAssignedTo();
        if ($assignedId !== null) {
            $assignedUser = $utilisateurRepository->find((int) $assignedId);
            if ($assignedUser !== null) {
                $form->get('assignedUser')->setData($assignedUser);
            }
        }
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var \App\Entity\UserHandling\Utilisateur|null $assignedUser */
            $assignedUser = $form->get('assignedUser')->getData();
            $task->setAssignedTo($assignedUser?->getId());
            $task->setUpdatedAt(new \DateTime());
            $entityManager->flush();

            if ($back !== '') {
                return $this->redirect($back, Response::HTTP_SEE_OTHER);
            }

            return $this->redirectToRoute('app_task_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('task/edit.html.twig', [
            'task' => $task,
            'form' => $form,
            'back' => $back,
        ]);
    }

    #[Route('/{id}/edit-modal', name: 'app_task_edit_modal', methods: ['GET', 'POST'])]
    public function editModal(
        Request $request,
        Task $task,
        ProjectRepository $projectRepository,
        ProjectAssignmentRepository $projectAssignmentRepository,
        UtilisateurRepository $utilisateurRepository,
        EntityManagerInterface $entityManager
    ): Response
    {
        $currentUser = $utilisateurRepository->findFirstManagerOrFirst();
        $role = strtolower((string) ($currentUser?->getRole() ?? ''));
        $isManager = $role !== '' && str_contains($role, 'manager');
        if (!$isManager) {
            throw $this->createAccessDeniedException();
        }

        $memberIds = [];
        $pid = (int) ($task->getProjectId() ?? 0);
        if ($pid > 0) {
            $project = $projectRepository->find($pid);
            if ($project !== null) {
                $createdBy = $project->getCreatedBy();
                if ($createdBy !== null) {
                    $memberIds[(int) $createdBy] = true;
                }
                $assignedTo = $project->getAssignedTo();
                if ($assignedTo !== null) {
                    $memberIds[(int) $assignedTo] = true;
                }
            }
            foreach ($projectAssignmentRepository->getUserIdsByProjectId($pid) as $uid) {
                $memberIds[(int) $uid] = true;
            }
        }

        $form = $this->createForm(TaskManagerUpdateType::class, $task, [
            'member_ids' => array_keys($memberIds),
        ]);

        $assignedId = $task->getAssignedTo();
        if ($assignedId !== null) {
            $assignedUser = $utilisateurRepository->find((int) $assignedId);
            if ($assignedUser !== null) {
                $form->get('assignedUser')->setData($assignedUser);
            }
        }

        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            if ($form->isValid()) {
                /** @var \App\Entity\UserHandling\Utilisateur|null $assignedUser */
                $assignedUser = $form->get('assignedUser')->getData();
                $task->setAssignedTo($assignedUser?->getId());
                $task->setUpdatedAt(new \DateTime());
                $entityManager->flush();

                if ($request->isXmlHttpRequest()) {
                    return new Response('', Response::HTTP_NO_CONTENT);
                }

                return $this->redirectToRoute('app_task_index', [], Response::HTTP_SEE_OTHER);
            }

            // Validation errors: return the modal content so the JS can re-render it.
            return $this->render('task/_edit_modal_content.html.twig', [
                'task' => $task,
                'form' => $form->createView(),
            ], new Response('', Response::HTTP_UNPROCESSABLE_ENTITY));
        }

        return $this->render('task/_edit_modal_content.html.twig', [
            'task' => $task,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}', name: 'app_task_delete', methods: ['POST'])]
    public function delete(Request $request, Task $task, UtilisateurRepository $utilisateurRepository, EntityManagerInterface $entityManager): Response
    {
        $currentUser = $utilisateurRepository->findFirstManagerOrFirst();
        $role = strtolower((string) ($currentUser?->getRole() ?? ''));
        $isManager = $role !== '' && str_contains($role, 'manager');
        if (!$isManager) {
            throw $this->createAccessDeniedException();
        }

        if ($this->isCsrfTokenValid('delete'.$task->getId(), (string) $request->request->get('_token', ''))) {
            $entityManager->remove($task);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_task_index', [], Response::HTTP_SEE_OTHER);
    }
}
