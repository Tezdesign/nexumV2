<?php

namespace App\Controller\Project;

use App\Entity\Projects\Project;
use App\Entity\Projects\ProjectAssignment;
use App\Entity\Tasks\Task;
use App\Form\Projects\ProjectManagerUpdateType;
use App\Form\Projects\ProjectQuickCreateType;
use App\Form\Tasks\TaskQuickCreateType;
use App\Repository\Projects\ProjectAssignmentRepository;
use App\Repository\Projects\ProjectRepository;
use App\Repository\Tasks\TaskRepository;
use App\Repository\UserHandling\UtilisateurRepository;
use App\Service\AuthService;
use App\Service\ProjectActivityFeed;
use App\Service\ProjectActivityLogger;
use App\Support\UserDisplayName;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/project')]
final class ProjectController extends AbstractController
{
    #[Route(name: 'app_project_index', methods: ['GET', 'POST'])]
    public function index(
        Request $request,
        ProjectRepository $projectRepository,
        ProjectAssignmentRepository $projectAssignmentRepository,
        TaskRepository $taskRepository,
        UtilisateurRepository $utilisateurRepository,
        AuthService $authService,
        ProjectActivityLogger $activityLogger,
        EntityManagerInterface $entityManager
    ): Response
    {
        $q = trim((string) $request->query->get('q', ''));
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

        // Data for the styled "Assigned to" dropdown in the create modal.
        $assignableUsers = [];
        $users = $utilisateurRepository->findNonAdminUsers();

        foreach ($users as $u) {
            $fullName = UserDisplayName::format($u, $u->getId());
            $role = trim((string) $u->getRole());
            $img = null;

            $raw = $u->getImagelink();
            if (is_string($raw)) {
                $raw = trim($raw);
                if ($raw !== '' && preg_match('~^(https?://|/|data:image/)~', $raw) === 1) {
                    $img = $raw;
                }
            }

            $assignableUsers[] = [
                'id' => $u->getId(),
                'name' => $fullName,
                'role' => $role,
                'img' => $img,
            ];
        }

        // Modal "quick create" form (same page, no navigation).
        $createProject = new Project();
        $createForm = $this->createForm(ProjectQuickCreateType::class, $createProject, [
            'action' => $this->generateUrl('app_project_index'),
            'method' => 'POST',
        ]);
        $createForm->handleRequest($request);
        $backUrl = (string) $request->request->get('back', $request->query->get('back', ''));
        $backUrl = ($backUrl !== '' && str_starts_with($backUrl, '/')) ? $backUrl : '';

        if ($createForm->isSubmitted() && !$isManager) {
            throw $this->createAccessDeniedException();
        }

        if ($createForm->isSubmitted() && $createForm->isValid()) {
            // Set legacy required field(s) that shouldn't be user-editable in the modal.
            $createProject->setCreatedBy((int) $currentUserId);

            $entityManager->persist($createProject);
            $entityManager->flush();

            $pid = $createProject->getId();
            if ($pid !== null) {
                /** @var iterable<\App\Entity\UserHandling\Utilisateur> $selectedUsers */
                $selectedUsers = $createForm->get('assignedUsers')->getData();

                $selectedUserIds = [];
                foreach ($selectedUsers as $u) {
                    if ($u->getId() !== null) {
                        $selectedUserIds[] = (int) $u->getId();
                    }
                }

                // Keep legacy single-assignee column for compatibility: pick the first selected user.
                if ($selectedUserIds !== []) {
                    $createProject->setAssignedTo($selectedUserIds[0]);
                    $entityManager->flush();
                }

                // Sync project_assignments for multi-user assignment.
                $memberIds = [];

                $creatorId = $createProject->getCreatedBy();
                if ($creatorId !== null) {
                    $memberIds[(int) $creatorId] = true;
                }
                foreach ($selectedUserIds as $uid) {
                    $memberIds[(int) $uid] = true;
                }

                foreach (array_keys($memberIds) as $uid) {
                    $pa = new ProjectAssignment();
                    $pa->setProject_id($pid);
                    $pa->setUserId((int) $uid);
                    $entityManager->persist($pa);
                }

                $actorName = UserDisplayName::format($currentUser, (int) $currentUserId);
                $memberCount = count($selectedUserIds);
                $activityLogger->record(
                    (int) $pid,
                    $actorName,
                    'project_created',
                    $memberCount > 0
                        ? sprintf('created project "%s" and assigned %d team member(s).', (string) $createProject->getName(), $memberCount)
                        : sprintf('created project "%s".', (string) $createProject->getName())
                );
                $entityManager->flush();
            }

            if ($request->isXmlHttpRequest()) {
                if ($backUrl !== '') {
                    return $this->json(['location' => $backUrl]);
                }

                return $this->json(['location' => $this->generateUrl('app_project_index')]);
            }

            if ($backUrl !== '') {
                return $this->redirect($backUrl, Response::HTTP_SEE_OTHER);
            }

            return $this->redirectToRoute('app_project_index', [], Response::HTTP_SEE_OTHER);
        }

        if ($createForm->isSubmitted() && !$createForm->isValid() && $request->isXmlHttpRequest()) {
            return $this->render('project/_create_modal_content.html.twig', [
                'createForm' => $createForm->createView(),
                'assignableUsers' => $assignableUsers,
                'backUrl' => $backUrl !== '' ? $backUrl : $this->generateUrl('app_project_index'),
            ], new Response('', Response::HTTP_UNPROCESSABLE_ENTITY));
        }

        $projects = $projectRepository->findForIndex($q);
        if (!$isManager) {
            $visibleProjectIds = $this->getVisibleProjectIdsForUser(
                (int) $currentUserId,
                $projectRepository,
                $projectAssignmentRepository
            );
            $visibleProjectIds = array_fill_keys($visibleProjectIds, true);

            $projects = array_values(array_filter(
                $projects,
                static fn (Project $project): bool => $project->getId() !== null && isset($visibleProjectIds[(int) $project->getId()])
            ));
        }

        $projectIds = [];
        foreach ($projects as $project) {
            if ($project->getId() !== null) {
                $projectIds[] = $project->getId();
            }
        }

        $projectProgressById = $taskRepository->getProgressPercentByProjectIds($projectIds);

        $assignedUserIdsByProjectId = $projectAssignmentRepository->getUserIdsByProjectIds($projectIds);

        // project_id => [memberId, memberId, ...]
        $memberIdsByProjectId = [];

        // Load the "card users" (created_by + assigned_to) in one query to avoid N+1 DB calls.
        $userIds = [];
        foreach ($projects as $project) {
            $pid = $project->getId();
            if ($pid === null) {
                continue;
            }

            // Prefer the assignment table for multi-user projects; keep legacy columns as fallback.
            $members = [];

            $createdBy = $project->getCreatedBy();
            if ($createdBy !== null) {
                $members[$createdBy] = true;
            }

            foreach (($assignedUserIdsByProjectId[$pid] ?? []) as $uid) {
                $members[(int) $uid] = true;
            }

            $assignedTo = $project->getAssignedTo();
            if ($assignedTo !== null) {
                $members[$assignedTo] = true;
            }

            $memberIdsByProjectId[$pid] = array_map('intval', array_keys($members));
            foreach ($implicitManagerIds as $managerId => $_) {
                $memberIdsByProjectId[$pid][] = (int) $managerId;
                $members[(int) $managerId] = true;
            }
            $memberIdsByProjectId[$pid] = array_values(array_unique(array_map('intval', $memberIdsByProjectId[$pid])));

            foreach ($memberIdsByProjectId[$pid] as $uid) {
                $userIds[$uid] = true;
            }
        }

        $usersById = $utilisateurRepository->findNonAdminIndexedByIds(array_keys($userIds));

        $avatarUrlById = [];
        foreach ($usersById as $uid => $user) {
            $raw = $user->getImagelink();
            if (is_string($raw)) {
                $raw = trim($raw);
                if ($raw !== '' && preg_match('~^(https?://|/|data:image/)~', $raw) === 1) {
                    $avatarUrlById[(int) $uid] = $raw;
                }
            }
        }

        return $this->render('project/index.html.twig', [
            'projects' => $projects,
            'q' => $q,
            'usersById' => $usersById,
            'avatarUrlById' => $avatarUrlById,
            'memberIdsByProjectId' => $memberIdsByProjectId,
            'projectProgressById' => $projectProgressById,
            'createForm' => $createForm->createView(),
            'assignableUsers' => $assignableUsers,
            'isManager' => $isManager,
        ]);
    }

    #[Route('/new', name: 'app_project_new', methods: ['GET', 'POST'])]
    public function new(Request $request, UtilisateurRepository $utilisateurRepository, AuthService $authService, EntityManagerInterface $entityManager): Response
    {
        $currentUserId = (int) ($authService->getCurrentUserId() ?? 0);
        $currentUser = $currentUserId > 0 ? $utilisateurRepository->find($currentUserId) : null;
        $isManager = $authService->isManager();
        if (!$isManager) {
            throw $this->createAccessDeniedException();
        }

        $project = new Project();
        $form = $this->createForm(ProjectManagerUpdateType::class, $project);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($project);
            $entityManager->flush();

            return $this->redirectToRoute('app_project_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('project/new.html.twig', [
            'project' => $project,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_project_show', methods: ['GET'])]
    public function show(
        Request $request,
        Project $project,
        ProjectRepository $projectRepository,
        ProjectAssignmentRepository $projectAssignmentRepository,
        ProjectActivityFeed $activityFeed,
        UtilisateurRepository $utilisateurRepository,
        AuthService $authService,
        TaskRepository $taskRepository
    ): Response
    {
        $pid = $project->getId();

        $currentUserId = (int) ($authService->getCurrentUserId() ?? 0);
        $currentUser = $currentUserId > 0 ? $utilisateurRepository->find($currentUserId) : null;
        $isManager = $authService->isManager();
        if (!$isManager) {
            $visibleProjectIds = array_fill_keys(
                $this->getVisibleProjectIdsForUser(
                    (int) $currentUserId,
                    $projectRepository,
                    $projectAssignmentRepository
                ),
                true
            );
            if ($project->getId() === null || !isset($visibleProjectIds[(int) $project->getId()])) {
                throw $this->createNotFoundException();
            }
        }

        $tab = strtolower(trim((string) $request->query->get('tab', 'overview')));
        $allowedTabs = ['overview', 'tasks', 'kanban', 'drafts'];
        if (!in_array($tab, $allowedTabs, true)) {
            $tab = 'overview';
        }

        $teamMemberIds = [];
        foreach ($utilisateurRepository->findManagerUsers() as $managerUser) {
            $managerId = $managerUser->getId();
            if ($managerId !== null) {
                $teamMemberIds[(int) $managerId] = true;
            }
        }
        $createdBy = $project->getCreatedBy();
        if ($createdBy !== null) {
            $teamMemberIds[$createdBy] = true;
        }

        $assignedTo = $project->getAssignedTo();
        if ($assignedTo !== null) {
            $teamMemberIds[$assignedTo] = true;
        }

        if ($pid !== null) {
            foreach ($projectAssignmentRepository->getUserIdsByProjectId((int) $pid) as $uid) {
                $teamMemberIds[(int) $uid] = true;
            }
        }

        $canEditProject = $isManager;
        $canDeleteProject = $isManager;
        $canDeleteTask = $isManager;
        $canCreateTask = $isManager || isset($teamMemberIds[(int) $currentUserId]);

        $createTask = null;
        $createTaskForm = null;
        if ($canCreateTask) {
            $createTask = new Task();
            $createTaskForm = $this->createForm(TaskQuickCreateType::class, $createTask, [
                'action' => $this->generateUrl('app_task_index'),
                'method' => 'POST',
                'is_manager' => $isManager,
                'allowed_project_ids' => $isManager ? [] : [$pid !== null ? (int) $pid : 0],
                'member_ids' => array_keys($teamMemberIds),
            ]);

            if ($pid !== null && $createTaskForm->has('project')) {
                $createTaskForm->get('project')->setData($project);
            }

            if ($isManager && $currentUser !== null && $createTaskForm->has('assignedUser')) {
                $createTaskForm->get('assignedUser')->setData($currentUser);
            }
        }

        $projectTasks = $pid !== null ? $taskRepository->findForProject((int) $pid) : [];
        $projectOverview = ProjectProgressEngine::build($project, $projectTasks);
        $projectProgressPercent = (int) ($projectOverview['completion_percentage'] ?? 0);
        $statusCounts = (array) ($projectOverview['status_counts'] ?? []);
        $stats = [
            'total' => (int) ($projectOverview['tasks_total'] ?? 0),
            'completed' => (int) ($statusCounts['done'] ?? 0),
            'overdue' => (int) ($projectOverview['tasks_overdue'] ?? 0),
        ];

        $recentActivities = $pid !== null ? $activityFeed->findRecentForProject((int) $pid, 6) : [];

        $tasks = [];
        $taskUserIds = [];
        if ($pid !== null && $tab === 'tasks') {
            $tasks = array_slice($projectTasks, 0, 200);
            foreach ($tasks as $task) {
                $au = $task->getAssignedTo();
                if ($au !== null) {
                    $taskUserIds[(int) $au] = true;
                }
                $cb = $task->getCreatedBy();
                if ($cb !== null) {
                    $taskUserIds[(int) $cb] = true;
                }
            }
        }

        $memberIds = array_map('intval', array_keys($teamMemberIds));
        $allUserIds = array_map('intval', array_keys($teamMemberIds + $taskUserIds));

        $membersById = $utilisateurRepository->findNonAdminIndexedByIds($allUserIds);
        $visibleMemberIds = array_values(array_filter(
            $memberIds,
            static fn (int $uid): bool => isset($membersById[$uid])
        ));
        $avatarUrlById = [];
        foreach ($membersById as $uid => $user) {
            $raw = $user->getImagelink();
            if (is_string($raw)) {
                $raw = trim($raw);
                if ($raw !== '' && preg_match('~^(https?://|/|data:image/)~', $raw) === 1) {
                    $avatarUrlById[(int) $uid] = $raw;
                }
            }
        }

        // Data for the "add members" modal.
        $allUsers = $utilisateurRepository->findNonAdminUsers();

        $pickableUsers = [];
        foreach ($allUsers as $u) {
            $id = $u->getId();
            if ($id === null) {
                continue;
            }
            $fullName = UserDisplayName::format($u, $id);
            $roleName = trim((string) $u->getRole());
            $img = null;

            $raw = $u->getImagelink();
            if (is_string($raw)) {
                $raw = trim($raw);
                if ($raw !== '' && preg_match('~^(https?://|/|data:image/)~', $raw) === 1) {
                    $img = $raw;
                }
            }

            $pickableUsers[] = [
                'id' => $id,
                'name' => $fullName,
                'role' => $roleName,
                'img' => $img,
            ];
        }

        return $this->render('project/show.html.twig', [
            'project' => $project,
            'isManager' => $isManager,
            'currentUserId' => (int) $currentUserId,
            'memberIds' => $memberIds,
            'visibleMemberIds' => $visibleMemberIds,
            'membersById' => $membersById,
            'avatarUrlById' => $avatarUrlById,
            'pickableUsers' => $pickableUsers,
            'stats' => $stats,
            'projectOverview' => $projectOverview,
            'tasks' => $tasks,
            'activeTab' => $tab,
            'canEditProject' => $canEditProject,
            'canDeleteProject' => $canDeleteProject,
            'canDeleteTask' => $canDeleteTask,
            'canCreateTask' => $canCreateTask,
            'createTaskForm' => $createTaskForm ? $createTaskForm->createView() : null,
            'projectProgressPercent' => $projectProgressPercent,
            'recentActivities' => $recentActivities,
        ]);
    }

    #[Route('/{id}/members', name: 'app_project_add_members', methods: ['POST'])]
    public function addMembers(
        Request $request,
        Project $project,
        ProjectAssignmentRepository $projectAssignmentRepository,
        UtilisateurRepository $utilisateurRepository,
        AuthService $authService,
        ProjectActivityLogger $activityLogger,
        EntityManagerInterface $entityManager
    ): Response
    {
        $pid = $project->getId();
        if ($pid === null) {
            return $this->redirectToRoute('app_project_index', [], Response::HTTP_SEE_OTHER);
        }

        $currentUserId = (int) ($authService->getCurrentUserId() ?? 0);
        $currentUser = $currentUserId > 0 ? $utilisateurRepository->find($currentUserId) : null;
        $isManager = $authService->isManager();
        if (!$isManager) {
            throw $this->createAccessDeniedException();
        }

        if (!$this->isCsrfTokenValid('project_members'.$pid, (string) $request->request->get('_token', ''))) {
            return $this->redirectToRoute('app_project_show', ['id' => $pid], Response::HTTP_SEE_OTHER);
        }

        $tab = strtolower(trim((string) $request->request->get('tab', 'overview')));
        $allowedTabs = ['overview', 'tasks', 'kanban', 'drafts'];
        if (!in_array($tab, $allowedTabs, true)) {
            $tab = 'overview';
        }

        $rawIds = $request->request->all('user_ids');
        $userIds = [];
        foreach ((array) $rawIds as $v) {
            $id = (int) $v;
            if ($id > 0) {
                $userIds[$id] = true;
            }
        }
        $userIds = array_keys($userIds);

        if ($userIds !== []) {
            // Existing members (including legacy columns).
            $existing = [];
            foreach ($utilisateurRepository->findManagerUsers() as $managerUser) {
                $managerId = $managerUser->getId();
                if ($managerId !== null) {
                    $existing[(int) $managerId] = true;
                }
            }
            $createdBy = $project->getCreatedBy();
            if ($createdBy !== null) {
                $existing[(int) $createdBy] = true;
            }
            $assignedTo = $project->getAssignedTo();
            if ($assignedTo !== null) {
                $existing[(int) $assignedTo] = true;
            }
            foreach ($projectAssignmentRepository->getUserIdsByProjectId((int) $pid) as $uid) {
                $existing[(int) $uid] = true;
            }

            // Ensure we only insert assignments for users that exist.
            $usersById = $utilisateurRepository->findNonAdminIndexedByIds($userIds);

            foreach ($userIds as $uid) {
                if (isset($existing[$uid])) {
                    continue;
                }
                if (!isset($usersById[$uid])) {
                    continue;
                }

                $pa = new ProjectAssignment();
                $pa->setProject_id((int) $pid);
                $pa->setUserId((int) $uid);
                $entityManager->persist($pa);
            }

            $addedNames = [];
            foreach ($userIds as $uid) {
                if (!isset($usersById[$uid])) {
                    continue;
                }
                $addedNames[] = UserDisplayName::format($usersById[$uid], $uid);
            }
            $addedNames = array_values(array_unique($addedNames));
            sort($addedNames, SORT_NATURAL | SORT_FLAG_CASE);

            if ($addedNames !== []) {
                $actorName = UserDisplayName::format($currentUser, (int) ($currentUser?->getId() ?? 0));
                $activityLogger->record(
                    (int) $pid,
                    $actorName,
                    'members_added',
                    'added team member(s): ' . implode(', ', $addedNames) . '.'
                );
            }

            $entityManager->flush();
        }

        return $this->redirectToRoute('app_project_show', ['id' => $pid, 'tab' => $tab], Response::HTTP_SEE_OTHER);
    }

    #[Route('/{id}/edit', name: 'app_project_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Project $project, ProjectAssignmentRepository $projectAssignmentRepository, UtilisateurRepository $utilisateurRepository, AuthService $authService, ProjectActivityLogger $activityLogger, EntityManagerInterface $entityManager): Response
    {
        $currentUserId = (int) ($authService->getCurrentUserId() ?? 0);
        $currentUser = $currentUserId > 0 ? $utilisateurRepository->find($currentUserId) : null;
        $isManager = $authService->isManager();
        if (!$isManager) {
            throw $this->createAccessDeniedException();
        }

        $assignableUsers = [];
        $assignableUserIds = [];
        foreach ($utilisateurRepository->findNonAdminUsers() as $user) {
            if ($user->getId() === null) {
                continue;
            }

            $assignableUserIds[] = (int) $user->getId();

            $raw = $user->getImagelink();
            $img = null;
            if (is_string($raw)) {
                $raw = trim($raw);
                if ($raw !== '' && preg_match('~^(https?://|/|data:image/)~', $raw) === 1) {
                    $img = $raw;
                }
            }

            $assignableUsers[] = [
                'id' => (int) $user->getId(),
                'name' => UserDisplayName::format($user, (int) $user->getId()),
                'role' => trim((string) $user->getRole()),
                'img' => $img,
            ];
        }

        $form = $this->createForm(ProjectManagerUpdateType::class, $project, [
            'assignable_users' => $assignableUserIds,
        ]);

        $prefillMemberIds = [];
        $createdBy = $project->getCreatedBy();
        if ($createdBy !== null) {
            $prefillMemberIds[(int) $createdBy] = true;
        }
        $assignedTo = $project->getAssignedTo();
        if ($assignedTo !== null) {
            $prefillMemberIds[(int) $assignedTo] = true;
        }
        foreach ($projectAssignmentRepository->getUserIdsByProjectId((int) $project->getId()) as $uid) {
            $prefillMemberIds[(int) $uid] = true;
        }
        if ($createdBy !== null) {
            unset($prefillMemberIds[(int) $createdBy]);
        }

        if ($form->has('assignedUsers') && $prefillMemberIds !== []) {
            $prefillUsers = $utilisateurRepository->findIndexedByIds(array_keys($prefillMemberIds));
            $form->get('assignedUsers')->setData(array_values($prefillUsers));
        }

        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            if ($form->isValid()) {
                /** @var iterable<\App\Entity\UserHandling\Utilisateur> $selectedUsers */
                $selectedUsers = $form->get('assignedUsers')->getData();
                $selectedUserIds = [];
                foreach ($selectedUsers as $u) {
                    if ($u->getId() !== null) {
                        $selectedUserIds[] = (int) $u->getId();
                    }
                }

                $project->setAssignedTo($selectedUserIds !== [] ? $selectedUserIds[0] : null);

                $entityManager->createQueryBuilder()
                    ->delete(ProjectAssignment::class, 'pa')
                    ->andWhere('pa.project_id = :pid')
                    ->setParameter('pid', (int) $project->getId())
                    ->getQuery()
                    ->execute();

                $keepIds = [];
                if ($project->getCreatedBy() !== null) {
                    $keepIds[(int) $project->getCreatedBy()] = true;
                }
                foreach ($selectedUserIds as $uid) {
                    $keepIds[(int) $uid] = true;
                }

                foreach (array_keys($keepIds) as $uid) {
                    $pa = new ProjectAssignment();
                    $pa->setProject_id((int) $project->getId());
                    $pa->setUserId((int) $uid);
                    $entityManager->persist($pa);
                }

                $entityManager->flush();

                $activityLogger->record(
                    (int) $project->getId(),
                    UserDisplayName::format($currentUser, (int) ($currentUser?->getId() ?? 0)),
                    'project_updated',
                    sprintf('updated project "%s".', (string) $project->getName())
                );
                $entityManager->flush();

                if ($request->isXmlHttpRequest()) {
                    return new Response('', Response::HTTP_NO_CONTENT);
                }

                return $this->redirectToRoute('app_project_index', [], Response::HTTP_SEE_OTHER);
            }

            if ($request->isXmlHttpRequest()) {
                return $this->render('project/_edit_modal_content.html.twig', [
                    'project' => $project,
                    'form' => $form->createView(),
                    'assignableUsers' => $assignableUsers,
                ], new Response('', Response::HTTP_UNPROCESSABLE_ENTITY));
            }
        }

        if ($request->isXmlHttpRequest()) {
            return $this->render('project/_edit_modal_content.html.twig', [
                'project' => $project,
                'form' => $form->createView(),
                'assignableUsers' => $assignableUsers,
            ]);
        }

        return $this->render('project/edit.html.twig', [
            'project' => $project,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_project_delete', methods: ['POST'])]
    public function delete(Request $request, Project $project, TaskRepository $taskRepository, UtilisateurRepository $utilisateurRepository, AuthService $authService, ProjectActivityLogger $activityLogger, EntityManagerInterface $entityManager): Response
    {
        $currentUserId = (int) ($authService->getCurrentUserId() ?? 0);
        $currentUser = $currentUserId > 0 ? $utilisateurRepository->find($currentUserId) : null;
        $isManager = $authService->isManager();
        if (!$isManager) {
            throw $this->createAccessDeniedException();
        }

        if ($this->isCsrfTokenValid('delete'.$project->getId(), (string) $request->request->get('_token', ''))) {
            $pid = $project->getId();
            if ($pid !== null) {
                $activityLogger->record(
                    (int) $pid,
                    UserDisplayName::format($currentUser, (int) ($currentUser?->getId() ?? 0)),
                    'project_deleted',
                    sprintf('deleted project "%s".', (string) $project->getName())
                );

                // Ensure tasks are removed when their project is deleted.
                $taskRepository->deleteByProjectId((int) $pid);

                // Also clean assignment rows to avoid leaving orphan records.
                $entityManager->createQueryBuilder()
                    ->delete(ProjectAssignment::class, 'pa')
                    ->andWhere('pa.project_id = :pid')
                    ->setParameter('pid', (int) $pid)
                    ->getQuery()
                    ->execute();
            }

            $entityManager->remove($project);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_project_index', [], Response::HTTP_SEE_OTHER);
    }

    /**
     * @return int[]
     */
    private function getVisibleProjectIdsForUser(
        int $userId,
        ProjectRepository $projectRepository,
        ProjectAssignmentRepository $projectAssignmentRepository
    ): array {
        if ($userId <= 0) {
            return [];
        }

        return array_values(array_unique(array_merge(
            $projectRepository->getProjectIdsForUser($userId),
            $projectAssignmentRepository->getProjectIdsByUserId($userId),
        )));
    }
}
