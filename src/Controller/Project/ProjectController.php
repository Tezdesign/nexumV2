<?php

namespace App\Controller\Project;

use App\Controller\Trait\LocalRedirectTrait;
use App\Entity\Projects\Project;
use App\Entity\Projects\ProjectAssignment;
use App\Entity\Tasks\Task;
use App\Form\Projects\ProjectManagerUpdateType;
use App\Form\Projects\ProjectQuickCreateType;
use App\Form\Tasks\TaskQuickCreateType;
use App\Repository\Projects\ProjectAssignmentRepository;
use App\Repository\Projects\ProjectFileRepository;
use App\Repository\Projects\ProjectRepository;
use App\Repository\Tasks\TaskRepository;
use App\Repository\UserHandling\UtilisateurRepository;
use App\Service\AuthService;
use App\Service\ProjectActivityFeed;
use App\Service\ProjectActivityLogger;
use App\Support\UserDisplayName;
use App\Service\Project\AI\ProjectTaskSuggestionService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\UX\Chartjs\Builder\ChartBuilderInterface;
use Symfony\UX\Chartjs\Model\Chart;

#[Route('/project')]
final class ProjectController extends AbstractController
{
    use LocalRedirectTrait;

    #[Route(name: 'app_project_index', methods: ['GET', 'POST'])]
    public function index(
        Request $request,
        ProjectRepository $projectRepository,
        ProjectAssignmentRepository $projectAssignmentRepository,
        ProjectFileRepository $projectFileRepository,
        TaskRepository $taskRepository,
        UtilisateurRepository $utilisateurRepository,
        AuthService $authService,
        ProjectTaskSuggestionService $projectTaskSuggestionService,
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
        $backUrl = $this->localPath($backUrl);
        $aiTaskSuggestionsJson = (string) $request->request->get('ai_task_suggestions', '[]');

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

                $suggestedTaskCount = 0;
                $projectStart = $createProject->getStart_date();
                $projectEnd = $createProject->getEnd_date();
                if ($projectStart instanceof \DateTimeInterface && $projectEnd instanceof \DateTimeInterface) {
                    $suggestions = $projectTaskSuggestionService->normalizeAcceptedSuggestions(
                        $aiTaskSuggestionsJson,
                        (string) $createProject->getName(),
                        $projectStart,
                        $projectEnd
                    );

                    foreach ($suggestions as $suggestion) {
                        $task = (new Task())
                            ->setTitle($suggestion['title'])
                            ->setDescription($suggestion['description'])
                            ->setStatus($suggestion['status'])
                            ->setPriority($suggestion['priority'])
                            ->setStart_date(\DateTime::createFromInterface($projectStart))
                            ->setDue_date(new \DateTime($suggestion['due_date']))
                            ->setProject_id((int) $pid)
                            ->setAssigned_to(null)
                            ->setCreated_by((int) $currentUserId);

                        $entityManager->persist($task);
                        ++$suggestedTaskCount;
                    }
                }

                $actorName = UserDisplayName::format($currentUser, (int) $currentUserId);
                $memberCount = count($selectedUserIds);
                $activityLogger->record(
                    (int) $pid,
                    $actorName,
                    'project_created',
                    $this->buildProjectCreatedMessage((string) $createProject->getName(), $memberCount, $suggestedTaskCount)
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
                'aiTaskSuggestionsJson' => $aiTaskSuggestionsJson,
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
            'aiTaskSuggestionsJson' => $aiTaskSuggestionsJson,
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

    #[Route('/{id}', name: 'app_project_show', methods: ['GET', 'POST'])]
    public function show(
        Request $request,
        Project $project,
        ProjectRepository $projectRepository,
        ProjectAssignmentRepository $projectAssignmentRepository,
        ProjectFileRepository $projectFileRepository,
        ProjectActivityFeed $activityFeed,
        UtilisateurRepository $utilisateurRepository,
        AuthService $authService,
        TaskRepository $taskRepository,
        \App\Repository\FinancialAnalysis\ExpenseDraftRepository $expenseDraftRepository,
        \App\Repository\FinancialAnalysis\ProjectBudgetRepository $projectBudgetRepository,
        \App\Service\FinancialAnalysis\BudgetAdvService $budgetAdvService,
        EntityManagerInterface $entityManager,
        ChartBuilderInterface $chartBuilder
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
        $allowedTabs = ['overview', 'tasks', 'kanban', 'drafts', 'files'];
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
        $projectOverview = ProjectProgressEngine::build($projectTasks);
        $projectProgressPercent = (int) ($projectOverview['completion_percentage'] ?? 0);
        $stats = [
            'total' => (int) ($projectOverview['tasks_total'] ?? 0),
            'completed' => (int) ($projectOverview['tasks_completed'] ?? 0),
            'overdue' => (int) ($projectOverview['tasks_overdue'] ?? 0),
        ];
        $statusCounts = [
            'To Do' => 0,
            'In Progress' => 0,
            'Done' => 0,
        ];
        $priorityCounts = [
            'High' => 0,
            'Medium' => 0,
            'Low' => 0,
            'Other' => 0,
        ];

        foreach ($projectTasks as $projectTask) {
            $normalizedStatus = strtolower(trim((string) ($projectTask->getStatus() ?? '')));
            if (in_array($normalizedStatus, ['done', 'completed', 'complete', 'finished'], true)) {
                ++$statusCounts['Done'];
            } elseif (in_array($normalizedStatus, ['in_progress', 'in progress', 'progress', 'doing', 'started'], true)) {
                ++$statusCounts['In Progress'];
            } else {
                ++$statusCounts['To Do'];
            }

            $normalizedPriority = strtolower(trim((string) ($projectTask->getPriority() ?? '')));
            if ($normalizedPriority === 'high') {
                ++$priorityCounts['High'];
            } elseif ($normalizedPriority === 'medium') {
                ++$priorityCounts['Medium'];
            } elseif ($normalizedPriority === 'low') {
                ++$priorityCounts['Low'];
            } else {
                ++$priorityCounts['Other'];
            }
        }

        $statusChart = $chartBuilder->createChart(Chart::TYPE_DOUGHNUT);
        $statusChart->setData([
            'labels' => array_keys($statusCounts),
            'datasets' => [[
                'data' => array_values($statusCounts),
                'backgroundColor' => ['#94a3b8', '#7c3aed', '#10b981'],
                'borderWidth' => 0,
            ]],
        ]);
        $statusChart->setOptions([
            'plugins' => [
                'legend' => ['position' => 'bottom'],
            ],
            'maintainAspectRatio' => false,
        ]);

        $priorityChart = $chartBuilder->createChart(Chart::TYPE_DOUGHNUT);
        $priorityChart->setData([
            'labels' => array_keys($priorityCounts),
            'datasets' => [[
                'data' => array_values($priorityCounts),
                'backgroundColor' => ['#ef4444', '#f59e0b', '#22c55e', '#cbd5e1'],
                'borderWidth' => 0,
            ]],
        ]);
        $priorityChart->setOptions([
            'plugins' => [
                'legend' => ['position' => 'bottom'],
            ],
            'maintainAspectRatio' => false,
        ]);

        $completionChart = $chartBuilder->createChart(Chart::TYPE_BAR);
        $completionChart->setData([
            'labels' => ['Completed', 'Overdue'],
            'datasets' => [[
                'label' => 'Tasks',
                'data' => [$stats['completed'], $stats['overdue']],
                'backgroundColor' => ['#10b981', '#ef4444'],
                'borderRadius' => 10,
                'maxBarThickness' => 48,
            ]],
        ]);
        $completionChart->setOptions([
            'plugins' => [
                'legend' => ['display' => false],
            ],
            'scales' => [
                'y' => [
                    'beginAtZero' => true,
                    'ticks' => ['precision' => 0],
                ],
            ],
            'maintainAspectRatio' => false,
        ]);

        $recentActivities = $pid !== null ? $activityFeed->findRecentForProject((int) $pid, 6) : [];

        $tasks = [];
        $projectFiles = [];
        $kanbanColumns = [];
        $kanbanTasks = [];
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
        if ($pid !== null && $tab === 'kanban') {
            $kanbanTasks = $isManager
                ? $projectTasks
                : array_values(array_filter(
                    $projectTasks,
                    static fn (Task $task): bool => (int) ($task->getAssignedTo() ?? 0) === $currentUserId
                ));

            foreach ($kanbanTasks as $task) {
                $au = $task->getAssignedTo();
                if ($au !== null) {
                    $taskUserIds[(int) $au] = true;
                }
                $cb = $task->getCreatedBy();
                if ($cb !== null) {
                    $taskUserIds[(int) $cb] = true;
                }
            }

            $normalizeStatus = static function (?string $status): string {
                return strtolower(trim((string) $status));
            };
            $isCompleted = static function (?string $status) use ($normalizeStatus): bool {
                return in_array($normalizeStatus($status), ['done', 'completed', 'complete', 'finished'], true);
            };
            $isInProgress = static function (?string $status) use ($normalizeStatus): bool {
                return in_array($normalizeStatus($status), ['in_progress', 'in progress', 'progress', 'doing', 'started'], true);
            };

            $kanbanColumns = [
                ['key' => 'todo', 'label' => 'To Do', 'tasks' => []],
                ['key' => 'in_progress', 'label' => 'In Progress', 'tasks' => []],
                ['key' => 'done', 'label' => 'Done', 'tasks' => []],
            ];

            foreach ($kanbanTasks as $task) {
                $status = $normalizeStatus($task->getStatus());
                $columnIndex = match (true) {
                    $isCompleted($status) => 2,
                    $isInProgress($status) => 1,
                    default => 0,
                };
                $kanbanColumns[$columnIndex]['tasks'][] = $task;
            }
        }
        if ($pid !== null && $tab === 'files') {
            $projectFiles = $projectFileRepository->findForProject((int) $pid);

            foreach ($projectFiles as $projectFile) {
                $uploadedBy = $projectFile->getUploaded_by();
                if ($uploadedBy !== null) {
                    $taskUserIds[(int) $uploadedBy] = true;
                }
            }
        }

        $drafts = [];
        $draftUserIds = [];
        $createDraftForm = null;

        if ($tab === 'drafts') {
            $drafts = $expenseDraftRepository->findByProject($project);
            foreach ($drafts as $draft) {
                $cb = $draft->getCreatedBy();
                if ($cb !== null && $cb->getId() !== null) {
                    $draftUserIds[(int) $cb->getId()] = true;
                }
            }

            // Draft creation logic
            if ($isManager) {
                $newDraft = new \App\Entity\FinancialAnalysis\ExpenseDraft();
                $draftForm = $this->createForm(\App\Form\FinancialAnalysis\ExpenseDraftType::class, $newDraft, [
                    'project_id' => $pid,
                ]);

                $draftForm->handleRequest($request);

                if ($draftForm->isSubmitted() && $draftForm->isValid()) {
                    if ($currentUser) {
                        $newDraft->setCreatedBy($currentUser);
                    }

                    $budgetAdvService->evaluateDraft($newDraft);

                    return $this->redirectToRoute('app_project_show', ['id' => $pid, 'tab' => 'drafts'], Response::HTTP_SEE_OTHER);
                }

                $createDraftForm = $draftForm->createView();
            }
        }

        $memberIds = array_map('intval', array_keys($teamMemberIds));
        $allUserIds = array_map('intval', array_keys($teamMemberIds + $taskUserIds + $draftUserIds));

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

        if ($projectFiles !== []) {
            $mappedFiles = [];

            foreach ($projectFiles as $projectFile) {
                $uploadedBy = (int) ($projectFile->getUploaded_by() ?? 0);
                $uploader = $uploadedBy > 0 ? ($membersById[$uploadedBy] ?? null) : null;
                $bytes = $projectFile->getBytes();
                $mappedFiles[] = [
                    'id' => $projectFile->getId(),
                    'original_name' => $projectFile->getOriginal_name(),
                    'public_id' => $projectFile->getPublic_id(),
                    'resource_type' => $projectFile->getResource_type(),
                    'format' => $projectFile->getFormat(),
                    'bytes' => $bytes,
                    'size_label' => $this->formatBytes($bytes),
                    'secure_url' => $projectFile->getSecure_url(),
                    'created_at' => $projectFile->getCreated_at(),
                    'uploaded_by' => $uploadedBy > 0 ? $uploadedBy : null,
                    'uploaded_by_name' => $uploader !== null
                        ? UserDisplayName::format($uploader, $uploadedBy)
                        : 'Unknown uploader',
                ];
            }

            $projectFiles = $mappedFiles;
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

        // Fetch project budgets for the draft update/create modals
        $projectBudgets = $projectBudgetRepository->findBy(['project' => $project], ['name' => 'ASC']);

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
            'statusChart' => $statusChart,
            'priorityChart' => $priorityChart,
            'completionChart' => $completionChart,
            'projectOverview' => $projectOverview,
            'tasks' => $tasks,
            'drafts' => $drafts,
            'projectFiles' => $projectFiles,
            'kanbanColumns' => $kanbanColumns,
            'activeTab' => $tab,
            'canEditProject' => $canEditProject,
            'canDeleteProject' => $canDeleteProject,
            'canDeleteTask' => $canDeleteTask,
            'canCreateTask' => $canCreateTask,
            'canUploadProjectFiles' => $currentUserId > 0,
            'projectFileUploadLimit' => (string) ini_get('upload_max_filesize'),
            'createTaskForm' => $createTaskForm ? $createTaskForm->createView() : null,
            'createDraftForm' => $createDraftForm,
            'projectProgressPercent' => $projectProgressPercent,
            'recentActivities' => $recentActivities,
            'projectBudgets' => $projectBudgets,
        ]);
    }

    private function formatBytes(?int $bytes): ?string
    {
        if ($bytes === null || $bytes < 0) {
            return null;
        }

        if ($bytes < 1024) {
            return $bytes . ' B';
        }

        $units = ['KB', 'MB', 'GB', 'TB'];
        $size = $bytes / 1024;
        $unitIndex = 0;

        while ($size >= 1024 && $unitIndex < count($units) - 1) {
            $size /= 1024;
            ++$unitIndex;
        }

        $precision = $size >= 10 ? 0 : 1;

        return number_format($size, $precision) . ' ' . $units[$unitIndex];
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
        $allowedTabs = ['overview', 'tasks', 'kanban', 'drafts','files'];
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

    #[Route('/{id}/delete', name: 'app_project_delete', methods: ['POST'])]
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
            else {
                dd('FAIL: The CSRF token did not match.');
            }

            $entityManager->remove($project);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_project_index', [], Response::HTTP_SEE_OTHER);
    }

    #[Route('/draft/{id}/update', name: 'app_project_update_draft', methods: ['POST'])]
    public function updateDraft(
        Request $request,
        \App\Entity\FinancialAnalysis\ExpenseDraft $draft,
        \App\Repository\FinancialAnalysis\ExpenseDraftRepository $expenseDraftRepository,
        \App\Repository\FinancialAnalysis\ProjectBudgetRepository $projectBudgetRepository,
        AuthService $authService,
        \Symfony\Component\Validator\Validator\ValidatorInterface $validator,
        \App\Service\FinancialAnalysis\BudgetAdvService $budgetAdvService,
        EntityManagerInterface $entityManager
    ): Response {
        if (!$authService->isManager()) {
            throw $this->createAccessDeniedException();
        }

        $projectBudgetRelated = $draft->getProjectBudgetRelated();
        $project = $projectBudgetRelated ? $projectBudgetRelated->getProject() : null;
        $pid = $project ? $project->getId() : 0;

        $data = $request->request->all('expense_draft');

        if (isset($data['subject'])) $draft->setSubject($data['subject']);
        if (isset($data['amount'])) $draft->setAmount((float) $data['amount']);
        if (isset($data['category'])) $draft->setCategory($data['category']);
        if (isset($data['description'])) $draft->setDescription($data['description']);

        if (!empty($data['project_budget_related'])) {
            $budget = $projectBudgetRepository->find($data['project_budget_related']);
            if ($budget) {
                $draft->setProjectBudgetRelated($budget);
            }
        }

        if (!$this->isCsrfTokenValid('expense_draft', (string) ($data['_token'] ?? ''))) {
            $this->addFlash('danger', 'Invalid CSRF token.');
            return $this->redirectToRoute('app_project_show', ['id' => $pid, 'tab' => 'drafts']);
        }

        $errors = $validator->validate($draft);

        if (count($errors) > 0) {
            $this->addFlash('danger', 'Failed to update draft. Please check the errors.');

            $errorMap = [];
            foreach ($errors as $error) {
                $errorMap[$error->getPropertyPath()] = $error->getMessage();
            }

            $session = $request->getSession();
            $this->addFlash('draft_errors_' . $draft->getId(), $errorMap);
            $this->addFlash('draft_data_' . $draft->getId(), $data);

            return $this->redirectToRoute('app_project_show', ['id' => $pid, 'tab' => 'drafts']);
        }

        $budgetAdvService->evaluateDraft($draft);
        $this->addFlash('success', 'Draft updated successfully!');

        return $this->redirectToRoute('app_project_show', ['id' => $pid, 'tab' => 'drafts']);
    }

    #[Route('/draft/{id}/delete', name: 'app_project_delete_draft', methods: ['POST'])]
    public function deleteDraft(
        Request $request,
        \App\Entity\FinancialAnalysis\ExpenseDraft $draft,
        AuthService $authService,
        EntityManagerInterface $entityManager
    ): Response {
        if (!$authService->isManager()) {
            throw $this->createAccessDeniedException();
        }

        $projectBudget = $draft->getProjectBudgetRelated();
        $project = $projectBudget ? $projectBudget->getProject() : null;
        $pid = $project ? $project->getId() : 0;

        if ($this->isCsrfTokenValid('delete_draft' . $draft->getId(), (string) $request->request->get('_token'))) {
            $entityManager->remove($draft);
            $entityManager->flush();
            $this->addFlash('success', 'Draft deleted successfully!');
        } else {
            $this->addFlash('danger', 'Invalid CSRF token for deletion.');
        }

        return $this->redirectToRoute('app_project_show', ['id' => $pid, 'tab' => 'drafts']);
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

    private function buildProjectCreatedMessage(string $projectName, int $memberCount, int $suggestedTaskCount): string
    {
        $base = $memberCount > 0
            ? sprintf('created project "%s" and assigned %d team member(s).', $projectName, $memberCount)
            : sprintf('created project "%s".', $projectName);

        if ($suggestedTaskCount <= 0) {
            return $base;
        }

        return sprintf('%s Added %d AI-suggested task(s).', $base, $suggestedTaskCount);
    }

}
