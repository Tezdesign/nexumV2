<?php

namespace App\Controller\tasks;

use App\Controller\Project\ProjectProgressEngine;
use App\Entity\Tasks\Task;
use App\Form\Tasks\TaskManagerUpdateType;
use App\Form\Tasks\TaskQuickCreateType;
use App\Form\Tasks\TaskType;
use App\Form\Tasks\TaskUpdateType;
use App\Repository\Projects\ProjectAssignmentRepository;
use App\Repository\Projects\ProjectRepository;
use App\Repository\Tasks\TaskRepository;
use App\Repository\UserHandling\UtilisateurRepository;
use App\Service\AuthService;
use App\Service\ProjectActivityLogger;
use App\Support\UserDisplayName;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormInterface;
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
        AuthService $authService,
        ProjectActivityLogger $activityLogger,
        EntityManagerInterface $entityManager
    ): Response
    {
        $q = trim((string) $request->query->get('q', ''));
        $statusFilter = strtolower(trim((string) $request->query->get('status', '')));
        $priorityFilter = strtolower(trim((string) $request->query->get('priority', '')));
        $statusFilter = in_array($statusFilter, ['todo', 'in_progress', 'done'], true) ? $statusFilter : '';
        $priorityFilter = in_array($priorityFilter, ['high', 'medium', 'low'], true) ? $priorityFilter : '';

        $currentUserId = (int) ($authService->getCurrentUserId() ?? 0);
        $currentUser = $currentUserId > 0 ? $utilisateurRepository->find($currentUserId) : null;
        $isManager = $authService->isManager();
        $implicitManagerIds = [];
        foreach ($utilisateurRepository->findManagerUsers() as $managerUser) {
            $managerId = $managerUser->getId();
            if ($managerId !== null) {
                $implicitManagerIds[(int) $managerId] = true;
            }
        }
        $allowedProjectIds = $isManager
            ? []
            : array_values(array_unique(array_merge(
                $projectRepository->getProjectIdsForUser((int) $currentUserId),
                $projectAssignmentRepository->getProjectIdsByUserId((int) $currentUserId),
            )));
        $canCreateTask = $isManager || $allowedProjectIds !== [];

        $prefillProjectId = (int) $request->query->get('project', 0);
        $openCreate = (string) $request->query->get('create', '') === '1';
        $prefillProject = $prefillProjectId > 0 ? $projectRepository->find($prefillProjectId) : null;
        $backUrl = (string) $request->request->get('back', $request->query->get('back', ''));
        $backUrl = ($backUrl !== '' && str_starts_with($backUrl, '/')) ? $backUrl : '';
        $forceSelfAssign = $isManager && (string) $request->query->get('dashboard_self', '') === '1';

        $createTaskMemberIds = [];
        $projectContext = $prefillProject;
        if ($projectContext === null) {
            foreach ($request->request->all() as $payload) {
                if (is_array($payload) && array_key_exists('project', $payload)) {
                    $submittedProjectId = (int) ($payload['project'] ?? 0);
                    if ($submittedProjectId > 0) {
                        $projectContext = $projectRepository->find($submittedProjectId);
                    }
                    break;
                }
            }
        }

        if ($projectContext !== null && $projectContext->getId() !== null) {
            $projectMemberIds = [];
            foreach ($implicitManagerIds as $managerId => $_) {
                $projectMemberIds[(int) $managerId] = true;
            }
            $createdBy = $projectContext->getCreatedBy();
            if ($createdBy !== null) {
                $projectMemberIds[(int) $createdBy] = true;
            }
            $assignedTo = $projectContext->getAssignedTo();
            if ($assignedTo !== null) {
                $projectMemberIds[(int) $assignedTo] = true;
            }
            foreach ($projectAssignmentRepository->getUserIdsByProjectId((int) $projectContext->getId()) as $uid) {
                $projectMemberIds[(int) $uid] = true;
            }
            $createTaskMemberIds = array_keys($projectMemberIds);
        }

        // Modal "quick create" form.
        $createTask = new Task();
        $createForm = $this->createForm(TaskQuickCreateType::class, $createTask, [
            'action' => $this->generateUrl('app_task_index', $forceSelfAssign ? ['dashboard_self' => 1] : []),
            'method' => 'POST',
            'is_manager' => $isManager,
            'allowed_project_ids' => $allowedProjectIds,
            'member_ids' => $createTaskMemberIds,
            'force_self_assign' => $forceSelfAssign,
        ]);

        // Preselect project when arriving from a per-project "+" button.
        if ($prefillProject !== null) {
            $createForm->get('project')->setData($prefillProject);
        }

        // Default assignee to the current user (manager can change it).
        if ($isManager && !$forceSelfAssign && $currentUser !== null && $createForm->has('assignedUser')) {
            $createForm->get('assignedUser')->setData($currentUser);
        }
        $createForm->handleRequest($request);
        $createFormResponseStatus = Response::HTTP_OK;

        if ($createForm->isSubmitted()) {
            $canPersist = false;
            if ($createForm->isValid()) {
                /** @var \App\Entity\Projects\Project|null $project */
                $project = $createForm->get('project')->getData();
                if ($project === null || $project->getId() === null) {
                    $createForm->get('project')->addError(new FormError('Please select a project.'));
                } else {
                    if ($isManager && !$forceSelfAssign) {
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
                    } else {
                        $assignedUserId = (int) $currentUserId;
                    }

                    $this->applyWorkloadGuard($createForm, $taskRepository, $createTask, (int) $assignedUserId);

                    if ($createForm->isValid()) {
                        $canPersist = true;
                    }
                }
            }

            if ($canPersist) {
                /** @var \App\Entity\Projects\Project $project */
                $project = $createForm->get('project')->getData();
                $assignedUserId = ($isManager && !$forceSelfAssign)
                    ? (int) (($createForm->get('assignedUser')->getData()?->getId()) ?? 0)
                    : (int) $currentUserId;

                $createTask->setProjectId((int) $project->getId());
                $createTask->setAssignedTo($assignedUserId > 0 ? $assignedUserId : (int) $currentUserId);
                $createTask->setCreatedBy((int) $currentUserId);
                $entityManager->persist($createTask);
                $entityManager->flush();

                $activityLogger->record(
                    (int) $project->getId(),
                    UserDisplayName::format($currentUser, (int) $currentUserId),
                    'task_created',
                    sprintf('created task "%s".', (string) $createTask->getTitle())
                );
                $entityManager->flush();

                $back = (string) $request->request->get('back', '');
                if ($back !== '' && str_starts_with($back, '/')) {
                    if ($request->isXmlHttpRequest()) {
                        return $this->json(['location' => $back]);
                    }

                    return $this->redirect($back, Response::HTTP_SEE_OTHER);
                }

                if ($request->isXmlHttpRequest()) {
                    return $this->json(['location' => $this->generateUrl('app_task_index')]);
                }

                return $this->redirectToRoute('app_task_index', [], Response::HTTP_SEE_OTHER);
            }

            if ($request->isXmlHttpRequest()) {
                $createFormResponseStatus = Response::HTTP_UNPROCESSABLE_ENTITY;
            }
        }

        return $this->render('task/index.html.twig', [
            'q' => $q,
            'statusFilter' => $statusFilter,
            'priorityFilter' => $priorityFilter,
            'isManager' => $isManager,
            'openCreate' => $openCreate,
            'prefillProjectId' => $prefillProjectId,
            'createForm' => $createForm->createView(),
            'canCreateTask' => $canCreateTask,
            'backUrl' => $backUrl,
        ], new Response('', $createFormResponseStatus));
    }

    #[Route('/assignees', name: 'app_task_assignees', methods: ['GET'])]
    public function assignees(
        Request $request,
        ProjectRepository $projectRepository,
        ProjectAssignmentRepository $projectAssignmentRepository,
        UtilisateurRepository $utilisateurRepository,
        AuthService $authService
    ): JsonResponse
    {
        $currentUserId = (int) ($authService->getCurrentUserId() ?? 0);
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

        foreach ($utilisateurRepository->findManagerUsers() as $managerUser) {
            $managerId = $managerUser->getId();
            if ($managerId !== null) {
                $memberIds[(int) $managerId] = true;
            }
        }

        $usersById = $utilisateurRepository->findNonAdminIndexedByIds(array_keys($memberIds));
        $users = [];
        foreach ($usersById as $u) {
            $id = $u->getId();
            if ($id === null) {
                continue;
            }
            $name = UserDisplayName::format($u, $id);
            $users[] = [
                'id' => (int) $id,
                'name' => $name,
            ];
        }

        usort($users, static fn (array $a, array $b): int => strcmp((string) $a['name'], (string) $b['name']));

        return $this->json(['users' => $users]);
    }

    #[Route('/workload-preview', name: 'app_task_workload_preview', methods: ['GET'])]
    public function workloadPreview(
        Request $request,
        TaskRepository $taskRepository,
        ProjectRepository $projectRepository,
        ProjectAssignmentRepository $projectAssignmentRepository,
        UtilisateurRepository $utilisateurRepository,
        AuthService $authService
    ): JsonResponse
    {
        if (($authService->getCurrentUserId() ?? 0) <= 0 || !$authService->isManager()) {
            return $this->json(['ok' => false], Response::HTTP_FORBIDDEN);
        }

        $projectId = (int) $request->query->get('project_id', 0);
        $allowedUserIds = $this->getAllowedPreviewAssigneeIds($projectId, $projectRepository, $projectAssignmentRepository, $utilisateurRepository);
        if ($allowedUserIds === []) {
            return $this->json(['ok' => false], Response::HTTP_FORBIDDEN);
        }

        $assignedUserId = (int) $request->query->get('assigned_user', 0);
        if ($assignedUserId <= 0) {
            return $this->json([
                'ok' => true,
                'decision' => 'idle',
                'label' => 'Select an assignee',
                'message' => 'Choose a team member to preview workload.',
                'score' => 0,
                'active_tasks' => 0,
                'in_progress_count' => 0,
                'overdue_count' => 0,
                'reasons' => [],
            ]);
        }
        if (!in_array($assignedUserId, $allowedUserIds, true)) {
            return $this->json(['ok' => false], Response::HTTP_FORBIDDEN);
        }

        $task = $this->buildPreviewTask($request, $assignedUserId);
        $excludeTaskId = (int) $request->query->get('exclude_task_id', 0);
        $tasks = $taskRepository->findActiveAssignedTasksForUser($assignedUserId, $excludeTaskId > 0 ? $excludeTaskId : null);
        $tasks[] = $task;
        $assessment = TaskWorkloadEngine::assess($tasks);

        return $this->json(array_merge(
            ['ok' => true],
            $this->formatWorkloadFeedback($assessment)
        ));
    }

    #[Route('/workload-preview/options', name: 'app_task_workload_preview_options', methods: ['GET'])]
    public function workloadPreviewOptions(
        Request $request,
        TaskRepository $taskRepository,
        ProjectRepository $projectRepository,
        ProjectAssignmentRepository $projectAssignmentRepository,
        UtilisateurRepository $utilisateurRepository,
        AuthService $authService
    ): JsonResponse
    {
        if (($authService->getCurrentUserId() ?? 0) <= 0 || !$authService->isManager()) {
            return $this->json(['ok' => false], Response::HTTP_FORBIDDEN);
        }

        $projectId = (int) $request->query->get('project_id', 0);
        $allowedUserIds = $this->getAllowedPreviewAssigneeIds($projectId, $projectRepository, $projectAssignmentRepository, $utilisateurRepository);
        if ($allowedUserIds === []) {
            return $this->json(['ok' => false], Response::HTTP_FORBIDDEN);
        }

        $requestedUserIds = array_values(array_unique(array_filter(
            array_map('intval', (array) $request->query->all('user_ids')),
            static fn (int $id): bool => $id > 0
        )));

        $userIds = array_values(array_intersect($requestedUserIds, $allowedUserIds));
        if ($userIds === []) {
            return $this->json(['ok' => true, 'users' => []]);
        }

        $excludeTaskId = (int) $request->query->get('exclude_task_id', 0);
        $groupedTasks = $taskRepository->findActiveAssignedTasksGroupedByUsers($userIds, $excludeTaskId > 0 ? $excludeTaskId : null);
        $users = [];

        foreach ($userIds as $userId) {
            $previewTask = $this->buildPreviewTask($request, $userId);
            $tasks = $groupedTasks[$userId] ?? [];
            $tasks[] = $previewTask;
            $feedback = $this->formatWorkloadFeedback(TaskWorkloadEngine::assess($tasks));

            $users[] = [
                'id' => $userId,
                'decision' => $feedback['decision'],
                'label' => $feedback['label'],
                'score' => $feedback['score'],
            ];
        }

        return $this->json([
            'ok' => true,
            'users' => $users,
        ]);
    }

    #[Route('/new', name: 'app_task_new', methods: ['GET', 'POST'])]
    public function new(Request $request, TaskRepository $taskRepository, UtilisateurRepository $utilisateurRepository, AuthService $authService, ProjectActivityLogger $activityLogger, EntityManagerInterface $entityManager): Response
    {
        $task = new Task();
        // Keep the legacy CRUD route around; UI uses the modal on /task.
        $form = $this->createForm(TaskType::class, $task);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $assignedUserId = (int) ($task->getAssignedTo() ?? 0);
            if ($assignedUserId > 0) {
                $this->applyWorkloadGuard($form, $taskRepository, $task, $assignedUserId);
            }
        }

        if ($form->isSubmitted() && $form->isValid()) {
            $currentUserId = (int) ($authService->getCurrentUserId() ?? 0);
            $currentUser = $currentUserId > 0 ? $utilisateurRepository->find($currentUserId) : null;
            $entityManager->persist($task);
            $entityManager->flush();

            if ($task->getProjectId() !== null) {
                $activityLogger->record(
                    (int) $task->getProjectId(),
                    UserDisplayName::format($currentUser, (int) ($currentUser?->getId() ?? 0)),
                    'task_created',
                    sprintf('created task "%s".', (string) $task->getTitle())
                );
                $entityManager->flush();
            }

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
        $projectId = (int) ($task->getProjectId() ?? 0);
        if ($projectId > 0) {
            return $this->redirectToRoute('app_project_show', ['id' => $projectId, 'tab' => 'tasks']);
        }

        return $this->redirectToRoute('app_task_index');
    }

    #[Route('/{id}/show-modal', name: 'app_task_show_modal', methods: ['GET'])]
    public function showModal(Task $task, UtilisateurRepository $utilisateurRepository, AuthService $authService): Response
    {
        $currentUserId = (int) ($authService->getCurrentUserId() ?? 0);
        $isManager = $authService->isManager();
        $canEditTask = (int) ($task->getCreatedBy() ?? 0) === $currentUserId;
        $assignedUserId = (int) ($task->getAssignedTo() ?? 0);
        $assignedUser = $assignedUserId > 0 ? $utilisateurRepository->find($assignedUserId) : null;
        $assignedName = trim((string) (($assignedUser?->getPrenom() ?? '').' '.($assignedUser?->getNom() ?? '')));

        return $this->render('task/_show_modal.html.twig', [
            'task' => $task,
            'assignedName' => $assignedName,
            'canEditTask' => $canEditTask,
            'canDeleteTask' => $isManager || ($task->getCreatedBy() !== null && (int) $task->getCreatedBy() === $currentUserId),
        ]);
    }

    #[Route('/{id}/status', name: 'app_task_status', methods: ['POST'])]
    public function status(Request $request, Task $task, TaskRepository $taskRepository, ProjectRepository $projectRepository, UtilisateurRepository $utilisateurRepository, AuthService $authService, ProjectActivityLogger $activityLogger, EntityManagerInterface $entityManager): Response
    {
        $currentUserId = (int) ($authService->getCurrentUserId() ?? 0);
        $currentUser = $currentUserId > 0 ? $utilisateurRepository->find($currentUserId) : null;
        $isSelfTask = $task->getAssignedTo() !== null && (int) $task->getAssignedTo() === $currentUserId;

        // Employees can update progress only for tasks assigned to them.
        // Managers can update progress only for tasks assigned to themselves.
        if (!$isSelfTask) {
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
        $entityManager->flush();

        $projectId = (int) ($task->getProjectId() ?? 0);
        if ($projectId > 0) {
            $project = $projectRepository->find($projectId);
            if ($project !== null) {
                $projectTasks = $taskRepository->findForProject($projectId);
                $projectOverview = ProjectProgressEngine::build($projectTasks);
                $project->setProgress((int) ($projectOverview['completion_percentage'] ?? 0));
                $entityManager->flush();
            }

            $statusLabel = match ($status) {
                'done' => 'done',
                'in_progress' => 'in progress',
                default => 'to do',
            };
            $activityLogger->record(
                $projectId,
                UserDisplayName::format($currentUser, $currentUserId),
                'task_status_changed',
                sprintf('changed task "%s" status to %s.', (string) $task->getTitle(), $statusLabel)
            );
            $entityManager->flush();
        }

        if ($request->isXmlHttpRequest()) {
            return new Response('', Response::HTTP_NO_CONTENT);
        }

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
        TaskRepository $taskRepository,
        ProjectRepository $projectRepository,
        ProjectAssignmentRepository $projectAssignmentRepository,
        UtilisateurRepository $utilisateurRepository,
        AuthService $authService,
        ProjectActivityLogger $activityLogger,
        EntityManagerInterface $entityManager
    ): Response
    {
        $currentUserId = (int) ($authService->getCurrentUserId() ?? 0);
        $currentUser = $currentUserId > 0 ? $utilisateurRepository->find($currentUserId) : null;
        $isManager = $authService->isManager();
        $canEditOwnTask = (int) ($task->getCreatedBy() ?? 0) === $currentUserId;
        if (!$canEditOwnTask) {
            throw $this->createNotFoundException();
        }
        $implicitManagerIds = [];
        foreach ($utilisateurRepository->findManagerUsers() as $managerUser) {
            $managerId = $managerUser->getId();
            if ($managerId !== null) {
                $implicitManagerIds[(int) $managerId] = true;
            }
        }

        $back = (string) $request->query->get('back', '');
        $back = ($back !== '' && str_starts_with($back, '/')) ? $back : '';

        $memberIds = [];
        foreach ($implicitManagerIds as $managerId => $_) {
            $memberIds[(int) $managerId] = true;
        }
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

        $form = $this->createForm(
            $isManager ? TaskManagerUpdateType::class : TaskUpdateType::class,
            $task,
            $isManager
                ? [
                    'member_ids' => array_keys($memberIds),
                    'allow_status' => ($task->getAssignedTo() !== null && (int) $task->getAssignedTo() === $currentUserId),
                ]
                : []
        );
        $originalAssignedUserId = (int) ($task->getAssignedTo() ?? 0);

        if ($isManager) {
            $assignedId = $task->getAssignedTo();
            if ($assignedId !== null) {
                $assignedUser = $utilisateurRepository->find((int) $assignedId);
                if ($assignedUser !== null && $form->has('assignedUser')) {
                    $form->get('assignedUser')->setData($assignedUser);
                }
            }
        }
        $form->handleRequest($request);

            if ($form->isSubmitted() && $form->isValid()) {
            if ($isManager) {
                /** @var \App\Entity\UserHandling\Utilisateur|null $assignedUser */
                $assignedUser = $form->get('assignedUser')->getData();
                $assignedUserId = (int) (($assignedUser?->getId()) ?? 0);
                if ($assignedUserId > 0 && $assignedUserId !== $originalAssignedUserId) {
                    $this->applyWorkloadGuard($form, $taskRepository, $task, (int) $assignedUserId, (int) ($task->getId() ?? 0));
                }
                if ($form->isValid()) {
                    $task->setAssignedTo($assignedUserId > 0 ? $assignedUserId : null);
                }
            }

            if ($form->isValid()) {
                $entityManager->flush();

                $projectId = (int) ($task->getProjectId() ?? 0);
                if ($projectId > 0) {
                    $activityLogger->record(
                        $projectId,
                        UserDisplayName::format($currentUser, $currentUserId),
                        'task_updated',
                        sprintf('updated task "%s".', (string) $task->getTitle())
                    );
                    $entityManager->flush();
                }

                if ($back !== '') {
                    return $this->redirect($back, Response::HTTP_SEE_OTHER);
                }

                return $this->redirectToRoute('app_task_index', [], Response::HTTP_SEE_OTHER);
            }
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
        TaskRepository $taskRepository,
        ProjectRepository $projectRepository,
        ProjectAssignmentRepository $projectAssignmentRepository,
        UtilisateurRepository $utilisateurRepository,
        AuthService $authService,
        ProjectActivityLogger $activityLogger,
        EntityManagerInterface $entityManager
    ): Response
    {
        $currentUserId = (int) ($authService->getCurrentUserId() ?? 0);
        $currentUser = $currentUserId > 0 ? $utilisateurRepository->find($currentUserId) : null;
        $isManager = $authService->isManager();
        $canEditOwnTask = (int) ($task->getCreatedBy() ?? 0) === $currentUserId;
        if (!$canEditOwnTask) {
            throw $this->createNotFoundException();
        }
        $implicitManagerIds = [];
        foreach ($utilisateurRepository->findManagerUsers() as $managerUser) {
            $managerId = $managerUser->getId();
            if ($managerId !== null) {
                $implicitManagerIds[(int) $managerId] = true;
            }
        }

        $memberIds = [];
        foreach ($implicitManagerIds as $managerId => $_) {
            $memberIds[(int) $managerId] = true;
        }
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

        $form = $this->createForm(
            $isManager ? TaskManagerUpdateType::class : TaskUpdateType::class,
            $task,
            $isManager
                ? [
                    'member_ids' => array_keys($memberIds),
                    'allow_status' => ($task->getAssignedTo() !== null && (int) $task->getAssignedTo() === $currentUserId),
                ]
                : []
        );
        $originalAssignedUserId = (int) ($task->getAssignedTo() ?? 0);

        if ($isManager) {
            $assignedId = $task->getAssignedTo();
            if ($assignedId !== null) {
                $assignedUser = $utilisateurRepository->find((int) $assignedId);
                if ($assignedUser !== null && $form->has('assignedUser')) {
                    $form->get('assignedUser')->setData($assignedUser);
                }
            }
        }

        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            if ($form->isValid()) {
                /** @var \App\Entity\UserHandling\Utilisateur|null $assignedUser */
                if ($isManager) {
                    $assignedUser = $form->get('assignedUser')->getData();
                    $assignedUserId = (int) (($assignedUser?->getId()) ?? 0);
                    if ($assignedUserId > 0 && $assignedUserId !== $originalAssignedUserId) {
                        $this->applyWorkloadGuard($form, $taskRepository, $task, (int) $assignedUserId, (int) ($task->getId() ?? 0));
                    }
                    if ($form->isValid()) {
                        $task->setAssignedTo($assignedUserId > 0 ? $assignedUserId : null);
                    }
                }

                if ($form->isValid()) {
                    $entityManager->flush();

                    $projectId = (int) ($task->getProjectId() ?? 0);
                    if ($projectId > 0) {
                        $activityLogger->record(
                            $projectId,
                            UserDisplayName::format($currentUser, $currentUserId),
                            'task_updated',
                            sprintf('updated task "%s".', (string) $task->getTitle())
                        );
                        $entityManager->flush();
                    }

                    if ($request->isXmlHttpRequest()) {
                        return new Response('', Response::HTTP_NO_CONTENT);
                    }

                    return $this->redirectToRoute('app_task_index', [], Response::HTTP_SEE_OTHER);
                }
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

    private function applyWorkloadGuard(FormInterface $form, TaskRepository $taskRepository, Task $pendingTask, int $assignedUserId, ?int $excludeTaskId = null): void
    {
        if ($assignedUserId <= 0) {
            return;
        }

        $tasks = $taskRepository->findActiveAssignedTasksForUser($assignedUserId, $excludeTaskId);
        $tasks[] = $pendingTask;
        $assessment = TaskWorkloadEngine::assess($tasks);

        if ($assessment['decision'] !== 'blocked') {
            return;
        }

        $feedback = $this->formatWorkloadFeedback($assessment);
        $message = (string) $feedback['message'];

        if ($form->has('assignedUser')) {
            $form->get('assignedUser')->addError(new FormError($message));
            return;
        }

        $form->addError(new FormError($message));
    }

    /**
     * @param array{
     *     decision?:string,
     *     score?:int,
     *     active_tasks?:int,
     *     in_progress_count?:int,
     *     overdue_count?:int,
     *     reasons?:array<int, string>
     * } $assessment
     *
     * @return array{
     *     decision:string,
     *     label:string,
     *     message:string,
     *     tone:string,
     *     score:int,
     *     active_tasks:int,
     *     in_progress_count:int,
     *     overdue_count:int,
     *     reasons:array<int, string>
     * }
     */
    private function formatWorkloadFeedback(array $assessment): array
    {
        $decision = (string) ($assessment['decision'] ?? 'ok');
        $score = (int) ($assessment['score'] ?? 0);
        $activeTasks = (int) ($assessment['active_tasks'] ?? 0);
        $inProgressCount = (int) ($assessment['in_progress_count'] ?? 0);
        $overdueCount = (int) ($assessment['overdue_count'] ?? 0);
        $reasons = array_values(array_filter(array_map('strval', (array) ($assessment['reasons'] ?? []))));

        $label = match ($decision) {
            'blocked' => 'Overloaded',
            'warning' => 'Heavy Load',
            'idle' => 'Select an assignee',
            default => 'Available',
        };

        $tone = match ($decision) {
            'blocked' => 'danger',
            'warning' => 'warning',
            'idle' => 'muted',
            default => 'success',
        };

        if ($decision === 'blocked') {
            $message = sprintf(
                'This assignment would overload the user. Score %d with %d active task(s), %d in progress, and %d overdue.',
                $score,
                $activeTasks,
                $inProgressCount,
                $overdueCount
            );
        } elseif ($decision === 'warning') {
            $message = sprintf(
                'This assignment would leave the user with a heavy workload. Score %d with %d active task(s), %d in progress, and %d overdue.',
                $score,
                $activeTasks,
                $inProgressCount,
                $overdueCount
            );
        } elseif ($decision === 'idle') {
            $message = 'Choose a team member to preview workload.';
        } else {
            $message = sprintf(
                'This assignment looks safe. Score %d with %d active task(s), %d in progress, and %d overdue.',
                $score,
                $activeTasks,
                $inProgressCount,
                $overdueCount
            );
        }

        return [
            'decision' => $decision,
            'label' => $label,
            'message' => $message,
            'tone' => $tone,
            'score' => $score,
            'active_tasks' => $activeTasks,
            'in_progress_count' => $inProgressCount,
            'overdue_count' => $overdueCount,
            'reasons' => $reasons,
        ];
    }

    private function buildPreviewTask(Request $request, int $assignedUserId): Task
    {
        $task = new Task();
        $task->setAssignedTo($assignedUserId);
        $task->setPriority((string) $request->query->get('priority', ''));
        $task->setStatus((string) $request->query->get('status', 'todo'));

        $dueDateRaw = trim((string) $request->query->get('due_date', ''));
        if ($dueDateRaw !== '') {
            try {
                $task->setDueDate(new \DateTime($dueDateRaw));
            } catch (\Throwable) {
                // Leave due date empty if client-side input is incomplete or invalid.
            }
        }

        return $task;
    }

    /**
     * @return int[]
     */
    private function getAllowedPreviewAssigneeIds(
        int $projectId,
        ProjectRepository $projectRepository,
        ProjectAssignmentRepository $projectAssignmentRepository,
        UtilisateurRepository $utilisateurRepository
    ): array {
        if ($projectId <= 0) {
            return [];
        }

        $project = $projectRepository->find($projectId);
        if ($project === null) {
            return [];
        }

        $memberIds = [];
        foreach ($utilisateurRepository->findManagerUsers() as $managerUser) {
            $managerId = $managerUser->getId();
            if ($managerId !== null) {
                $memberIds[(int) $managerId] = true;
            }
        }

        $createdBy = $project->getCreatedBy();
        if ($createdBy !== null) {
            $memberIds[(int) $createdBy] = true;
        }

        $assignedTo = $project->getAssignedTo();
        if ($assignedTo !== null) {
            $memberIds[(int) $assignedTo] = true;
        }

        foreach ($projectAssignmentRepository->getUserIdsByProjectId($projectId) as $uid) {
            $memberIds[(int) $uid] = true;
        }

        return array_map('intval', array_keys($memberIds));
    }

    #[Route('/{id}', name: 'app_task_delete', methods: ['POST'])]
    public function delete(Request $request, Task $task, UtilisateurRepository $utilisateurRepository, AuthService $authService, ProjectActivityLogger $activityLogger, EntityManagerInterface $entityManager): Response
    {
        $currentUserId = (int) ($authService->getCurrentUserId() ?? 0);
        $currentUser = $currentUserId > 0 ? $utilisateurRepository->find($currentUserId) : null;
        $isManager = $authService->isManager();

        $canDelete = $isManager || ($task->getCreatedBy() !== null && (int) $task->getCreatedBy() === $currentUserId);

        if (!$canDelete) {
            $back = (string) $request->request->get('back', '');
            if ($back !== '' && str_starts_with($back, '/')) {
                return $this->redirect($back, Response::HTTP_SEE_OTHER);
            }

            return $this->redirectToRoute('app_task_index', [], Response::HTTP_SEE_OTHER);
        }

        if ($this->isCsrfTokenValid('delete'.$task->getId(), (string) $request->request->get('_token', ''))) {
            if ($task->getProjectId() !== null) {
                $activityLogger->record(
                    (int) $task->getProjectId(),
                    UserDisplayName::format($currentUser, $currentUserId),
                    'task_deleted',
                    sprintf('deleted task "%s".', (string) $task->getTitle())
                );
                $entityManager->flush();
            }

            $entityManager->remove($task);
            $entityManager->flush();
        }

        $back = (string) $request->request->get('back', '');
        if ($back !== '' && str_starts_with($back, '/')) {
            return $this->redirect($back, Response::HTTP_SEE_OTHER);
        }

        return $this->redirectToRoute('app_task_index', [], Response::HTTP_SEE_OTHER);
    }
}
