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
use App\Service\AuthService;
use App\Service\ProjectActivityLogger;
use App\Support\UserDisplayName;

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
            'action' => $this->generateUrl('app_task_index'),
            'method' => 'POST',
            'is_manager' => $isManager,
            'allowed_project_ids' => $allowedProjectIds,
            'member_ids' => $createTaskMemberIds,
        ]);

        // Preselect project when arriving from a per-project "+" button.
        if ($prefillProject !== null) {
            $createForm->get('project')->setData($prefillProject);
        }

        // Default assignee to the current user (manager can change it).
        if ($isManager && $currentUser !== null && $createForm->has('assignedUser')) {
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
                    if ($isManager) {
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

                    if ($createForm->isValid()) {
                        $canPersist = true;
                    }
                }
            }

            if ($canPersist) {
                /** @var \App\Entity\Projects\Project $project */
                $project = $createForm->get('project')->getData();
                $assignedUserId = $isManager ? (int) (($createForm->get('assignedUser')->getData()?->getId()) ?? 0) : (int) $currentUserId;

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
        $usersById = $utilisateurRepository->findNonAdminIndexedByIds(array_keys($userIds));
 
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

    #[Route('/new', name: 'app_task_new', methods: ['GET', 'POST'])]
    public function new(Request $request, UtilisateurRepository $utilisateurRepository, AuthService $authService, ProjectActivityLogger $activityLogger, EntityManagerInterface $entityManager): Response
    {
        $task = new Task();
        // Keep the legacy CRUD route around; UI uses the modal on /task.
        $form = $this->createForm(TaskType::class, $task);
        $form->handleRequest($request);

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
    public function show(Task $task, UtilisateurRepository $utilisateurRepository, AuthService $authService): Response
    {
        $currentUserId = (int) ($authService->getCurrentUserId() ?? 0);
        $currentUser = $currentUserId > 0 ? $utilisateurRepository->find($currentUserId) : null;
        $isManager = $authService->isManager();
        $canEditTask = (int) ($task->getCreatedBy() ?? 0) === $currentUserId;

        return $this->render('task/show.html.twig', [
            'task' => $task,
            'currentUserId' => $currentUserId,
            'isManager' => $isManager,
            'canEditTask' => $canEditTask,
            'canDeleteTask' => $isManager || ($task->getCreatedBy() !== null && (int) $task->getCreatedBy() === $currentUserId),
        ]);
    }

    #[Route('/{id}/status', name: 'app_task_status', methods: ['POST'])]
    public function status(Request $request, Task $task, UtilisateurRepository $utilisateurRepository, AuthService $authService, ProjectActivityLogger $activityLogger, EntityManagerInterface $entityManager): Response
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
                $task->setAssignedTo($assignedUser?->getId());
            }
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
                    $task->setAssignedTo($assignedUser?->getId());
                }
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
