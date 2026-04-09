<?php

namespace App\Controller;

use App\Service\AuthService;
use App\Entity\Projects\Project;
use App\Form\Projects\ProjectQuickCreateType;
use App\Form\Tasks\TaskQuickCreateType;
use App\Repository\Projects\ProjectAssignmentRepository;
use App\Repository\Projects\ProjectRepository;
use App\Repository\Tasks\TaskRepository;
use App\Repository\UserHandling\UtilisateurRepository;
use App\Support\UserDisplayName;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class HomeController extends AbstractController
{
    private AuthService $authService;

    public function __construct(AuthService $authService)
    {
        $this->authService = $authService;
    }

    #[Route('/', name: 'dashboard')]
    public function index(
        ProjectRepository $projectRepository,
        ProjectAssignmentRepository $projectAssignmentRepository,
        TaskRepository $taskRepository,
        UtilisateurRepository $utilisateurRepository
    ): Response
    {
        // Check if user is authenticated with our custom session
        if (!$this->authService->isLoggedIn()) {
            return $this->redirectToRoute('welcome');
        }

        $currentUserId = (int) ($this->authService->getCurrentUserId() ?? 0);
        $currentUser = $currentUserId > 0 ? $utilisateurRepository->find($currentUserId) : null;
        $role = strtolower((string) ($this->authService->getCurrentUserRole() ?? ''));
        $isManager = $this->authService->isManager();
        $implicitManagerIds = [];
        foreach ($utilisateurRepository->findManagerUsers() as $managerUser) {
            $managerId = $managerUser->getId();
            if ($managerId !== null) {
                $implicitManagerIds[(int) $managerId] = true;
            }
        }

        $welcomeName = trim((string) ($currentUser?->getPrenom() ?? ''));
        if ($welcomeName === '') {
            $welcomeName = UserDisplayName::format($currentUser, $currentUserId);
        }

        $createProjectForm = null;
        $assignableUsers = [];
        if ($isManager) {
            $createProject = new Project();
            $createProjectForm = $this->createForm(ProjectQuickCreateType::class, $createProject, [
                'action' => $this->generateUrl('app_project_index'),
                'method' => 'POST',
            ]);

            $allUsers = $utilisateurRepository->findNonAdminUsers();

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

                $assignableUsers[] = [
                    'id' => $id,
                    'name' => $fullName,
                    'role' => $roleName,
                    'img' => $img,
                ];
            }
        }

        $relatedProjectIds = $isManager ? [] : array_values(array_unique(array_merge(
            $projectRepository->getProjectIdsForUser($currentUserId),
            $projectAssignmentRepository->getProjectIdsByUserId($currentUserId),
        )));

        $projects = $isManager
            ? $projectRepository->findForIndex()
            : array_values($projectRepository->findIndexedByIds($relatedProjectIds));

        usort($projects, static function (Project $left, Project $right): int {
            $leftDate = $left->getUpdatedAt() ?? $left->getCreatedAt();
            $rightDate = $right->getUpdatedAt() ?? $right->getCreatedAt();

            if ($leftDate instanceof \DateTimeInterface && $rightDate instanceof \DateTimeInterface) {
                return $rightDate <=> $leftDate;
            }

            if ($leftDate instanceof \DateTimeInterface) {
                return -1;
            }

            if ($rightDate instanceof \DateTimeInterface) {
                return 1;
            }

            return ((int) ($right->getId() ?? 0)) <=> ((int) ($left->getId() ?? 0));
        });

        $projectIds = [];
        foreach ($projects as $project) {
            $pid = $project->getId();
            if ($pid !== null) {
                $projectIds[] = (int) $pid;
            }
        }

        $projectProgressById = $taskRepository->getProgressPercentByProjectIds($projectIds);
        $projectMemberIdsByProjectId = $projectAssignmentRepository->getUserIdsByProjectIds($projectIds);

        $projectUserIds = [];
        foreach ($projects as $project) {
            $pid = (int) ($project->getId() ?? 0);
            if ($pid <= 0) {
                continue;
            }

            $createdBy = (int) ($project->getCreatedBy() ?? 0);
            if ($createdBy > 0) {
                $projectUserIds[$createdBy] = true;
            }

            $assignedTo = (int) ($project->getAssignedTo() ?? 0);
            if ($assignedTo > 0) {
                $projectUserIds[$assignedTo] = true;
            }

            foreach (($projectMemberIdsByProjectId[$pid] ?? []) as $uid) {
                $uid = (int) $uid;
                if ($uid > 0) {
                    $projectUserIds[$uid] = true;
                }
            }
        }

        foreach ($implicitManagerIds as $managerId => $_) {
            $projectUserIds[(int) $managerId] = true;
        }

        $usersById = $utilisateurRepository->findNonAdminIndexedByIds(array_keys($projectUserIds));
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

        $dashboardTasks = $isManager
            ? $taskRepository->findForManager()
            : $taskRepository->findForDashboardScope($currentUserId, $relatedProjectIds);

        $yourTasks = array_slice($taskRepository->findForDashboardUser((int) $currentUserId), 0, 6);

        $taskProjectIds = [];
        foreach (array_merge($dashboardTasks, $yourTasks) as $task) {
            $pid = (int) ($task->getProjectId() ?? 0);
            if ($pid > 0) {
                $taskProjectIds[$pid] = true;
            }
        }

        $projectsById = $projectRepository->findIndexedByIds(array_values(array_unique(array_merge(
            $projectIds,
            array_keys($taskProjectIds)
        ))));

        $createTaskForm = null;
        if ($isManager || $relatedProjectIds !== []) {
            $createTask = new \App\Entity\Tasks\Task();
            $createTaskForm = $this->createForm(TaskQuickCreateType::class, $createTask, [
                'action' => $this->generateUrl('app_task_index'),
                'method' => 'POST',
                'is_manager' => $isManager,
                'allowed_project_ids' => $relatedProjectIds,
            ]);

            if ($isManager && $currentUser !== null && $createTaskForm->has('assignedUser')) {
                $createTaskForm->get('assignedUser')->setData($currentUser);
            }
        }

        $today = new \DateTimeImmutable('today');
        $deadlineWindowEnd = $today->modify('+7 days');

        $normalizeStatus = static function (?string $status): string {
            return strtolower(trim((string) $status));
        };
        $isCompleted = static function (?string $status) use ($normalizeStatus): bool {
            return in_array($normalizeStatus($status), ['done', 'completed', 'complete', 'finished'], true);
        };
        $isInProgress = static function (?string $status) use ($normalizeStatus): bool {
            return in_array($normalizeStatus($status), ['in_progress', 'in progress', 'progress', 'doing', 'started'], true);
        };
        $formatDate = static function (?\DateTimeInterface $date, string $fallback = '—'): string {
            return $date instanceof \DateTimeInterface ? $date->format('d M Y') : $fallback;
        };
        $projectStatusForProgress = static function (int $progress): array {
            return match (true) {
                $progress >= 100 => ['done', 'Done'],
                $progress > 0 => ['in_progress', 'In Progress'],
                default => ['todo', 'To Do'],
            };
        };
        $projectPriorityForDeadline = static function (?\DateTimeInterface $endDate) use ($today): array {
            if (!$endDate instanceof \DateTimeInterface) {
                return ['low', 'Low'];
            }

            $deadline = \DateTimeImmutable::createFromInterface($endDate)->setTime(0, 0);
            if ($deadline < $today) {
                return ['high', 'High'];
            }

            $daysRemaining = (int) $today->diff($deadline)->days;
            if ($daysRemaining <= 3) {
                return ['high', 'High'];
            }

            if ($daysRemaining <= 7) {
                return ['medium', 'Medium'];
            }

            return ['low', 'Low'];
        };

        $tasksInProgress = 0;
        $completedTasks = 0;
        $upcomingDeadlines = 0;
        $overdueUndoneTasks = 0;
        foreach ($dashboardTasks as $task) {
            $status = $task->getStatus();
            $done = $isCompleted($status);
            $inProgress = $isInProgress($status);

            if ($inProgress) {
                $tasksInProgress++;
            }

            if ($done) {
                $completedTasks++;
            }

            $dueDate = $task->getDueDate();
            if ($dueDate instanceof \DateTimeInterface) {
                $dueDay = \DateTimeImmutable::createFromInterface($dueDate)->setTime(0, 0);
                if (!$done && $dueDay < $today) {
                    $overdueUndoneTasks++;
                }

                if (!$done && $dueDay >= $today && $dueDay <= $deadlineWindowEnd) {
                    $upcomingDeadlines++;
                }
            }
        }

        foreach ($projects as $project) {
            $endDate = $project->getEndDate();
            if (!$endDate instanceof \DateTimeInterface) {
                continue;
            }

            $endDay = \DateTimeImmutable::createFromInterface($endDate)->setTime(0, 0);
            if ($endDay >= $today && $endDay <= $deadlineWindowEnd) {
                $upcomingDeadlines++;
            }
        }

        $projectCards = [];
        foreach ($projects as $project) {
            $pid = $project->getId();
            if ($pid === null) {
                continue;
            }

            $projectName = trim((string) $project->getName());
            $projectName = $projectName !== '' ? $projectName : ('Project #' . $pid);
            $progress = (int) ($projectProgressById[$pid] ?? 0);
            [$statusKey, $statusLabel] = $projectStatusForProgress($progress);
            [$priorityKey, $priorityLabel] = $projectPriorityForDeadline($project->getEndDate());

            $memberIds = [];
            $createdBy = (int) ($project->getCreatedBy() ?? 0);
            if ($createdBy > 0) {
                $memberIds[$createdBy] = true;
            }

            $assignedTo = (int) ($project->getAssignedTo() ?? 0);
            if ($assignedTo > 0) {
                $memberIds[$assignedTo] = true;
            }

            foreach (($projectMemberIdsByProjectId[$pid] ?? []) as $uid) {
                $uid = (int) $uid;
                if ($uid > 0) {
                    $memberIds[$uid] = true;
                }
            }

            $memberIds = array_values(array_unique(array_map('intval', array_keys($memberIds))));

            $projectCards[] = [
                'id' => $pid,
                'name' => $projectName,
                'startLabel' => $formatDate($project->getStartDate()),
                'endLabel' => $formatDate($project->getEndDate()),
                'progress' => $progress,
                'statusKey' => $statusKey,
                'statusLabel' => $statusLabel,
                'priorityKey' => $priorityKey,
                'priorityLabel' => $priorityLabel,
                'memberIds' => $memberIds,
                'url' => $this->generateUrl('app_project_show', ['id' => $pid, 'tab' => 'overview']),
            ];
        }

        $taskCards = [];
        foreach ($yourTasks as $task) {
            $tid = $task->getId();
            if ($tid === null) {
                continue;
            }

            $projectId = (int) ($task->getProjectId() ?? 0);
            $project = $projectId > 0 ? ($projectsById[$projectId] ?? null) : null;
            $projectName = $project !== null ? trim((string) $project->getName()) : '';
            if ($projectName === '') {
                $projectName = $projectId > 0 ? ('Project #' . $projectId) : 'Unassigned';
            }

            $status = $normalizeStatus($task->getStatus());
            $statusKey = match (true) {
                $isCompleted($status) => 'done',
                $isInProgress($status) => 'in_progress',
                default => 'todo',
            };
            $statusLabel = match ($statusKey) {
                'done' => 'Done',
                'in_progress' => 'In Progress',
                default => 'To Do',
            };

            $priority = $normalizeStatus($task->getPriority());
            $priorityKey = match ($priority) {
                'high', 'medium', 'low' => $priority,
                default => 'low',
            };
            $priorityLabel = strtoupper($priorityKey);

            $priorityClass = match ($priorityKey) {
                'high' => 'high',
                'medium' => 'medium',
                'low' => 'low',
            };

            $taskCards[] = [
                'id' => $tid,
                'title' => trim((string) $task->getTitle()) ?: ('Task #' . $tid),
                'projectName' => $projectName,
                'assignedTo' => (int) ($task->getAssignedTo() ?? 0),
                'statusKey' => $statusKey,
                'statusLabel' => $statusLabel,
                'priorityKey' => $priorityKey,
                'priorityLabel' => $priorityLabel,
                'dueLabel' => $formatDate($task->getDueDate(), 'No deadline'),
                'completed' => $isCompleted($status),
                'url' => $this->generateUrl('app_task_show', ['id' => $tid]),
            ];
        }

        $metrics = [
            [
                'label' => 'Tasks In Progress',
                'value' => $tasksInProgress,
                'icon' => 'ti ti-loader-2',
                'tone' => 'warning',
                'danger' => false,
            ],
            [
                'label' => 'Completed Tasks',
                'value' => $completedTasks,
                'icon' => 'ti ti-circle-check',
                'tone' => 'success',
                'danger' => false,
            ],
            [
                'label' => 'Total Projects',
                'value' => count($projects),
                'icon' => 'ti ti-folder',
                'tone' => 'primary',
                'danger' => false,
            ],
            [
                'label' => 'Upcoming Deadlines',
                'value' => $upcomingDeadlines,
                'icon' => 'ti ti-calendar-event',
                'tone' => 'danger',
                'danger' => true,
            ],
            [
                'label' => 'Overdue Undone Tasks',
                'value' => $overdueUndoneTasks,
                'icon' => 'ti ti-alert-triangle',
                'tone' => 'danger',
                'danger' => true,
            ],
        ];

        return $this->render('index.html.twig', [
            'welcomeName' => $welcomeName,
            'isManager' => $isManager,
            'metrics' => $metrics,
            'projectCards' => $projectCards,
            'taskCards' => $taskCards,
            'currentUserId' => $currentUserId,
            'usersById' => $usersById,
            'avatarUrlById' => $avatarUrlById,
            'createProjectForm' => $createProjectForm ? $createProjectForm->createView() : null,
            'assignableUsers' => $assignableUsers,
            'createTaskForm' => $createTaskForm ? $createTaskForm->createView() : null,
            'canCreateTask' => $createTaskForm !== null,
            'projectCount' => count($projects),
            'taskCount' => count($yourTasks),
        ]);
    }

    #[Route('/apps-chat', name: 'apps-chat')]
    public function chat(): Response
    {
        return $this->render('chat/apps-chat.html.twig');
    }

    #[Route('/apps-projects', name: 'apps-projects')]
    public function projects(): Response
    {
        return $this->redirectToRoute('app_project_index');
    }

    #[Route('/apps-kanban', name: 'apps-kanban')]
    public function kanban(): Response
    {
        return $this->render('project-management/apps-kanban.html.twig');
    }

    #[Route('/apps-task-details', name: 'apps-task-details')]
    public function taskDetails(): Response
    {
        return $this->render('project-management/apps-task-details.html.twig');
    }

    #[Route('/apps-training', name: 'apps-training')]
    public function training(): Response
    {
        return $this->render('training/apps-training.html.twig');
    }

    #[Route('/apps-financial-analysis', name: 'apps-financial-analysis')]
    public function financialAnalysis(): Response
    {
        return $this->render('financial-analysis/apps-financial-analysis.html.twig');
    }

    #[Route('/apps-resources-management', name: 'apps-resources-management')]
    public function resourcesManagement(): Response
    {
        return $this->render('resources-management/apps-resources-management.html.twig');
    }

    #[Route('/apps-calendar', name: 'apps-calendar')]
    public function calendar(
        ProjectRepository $projectRepository,
        ProjectAssignmentRepository $projectAssignmentRepository,
        TaskRepository $taskRepository,
        UtilisateurRepository $utilisateurRepository
    ): Response
    {
        $currentUserId = (int) ($this->authService->getCurrentUserId() ?? 0);
        $currentUser = $currentUserId > 0 ? $utilisateurRepository->find($currentUserId) : null;
        $role = strtolower((string) ($this->authService->getCurrentUserRole() ?? ''));
        $isManager = $this->authService->isManager();
        $implicitManagerIds = [];
        foreach ($utilisateurRepository->findManagerUsers() as $managerUser) {
            $managerId = $managerUser->getId();
            if ($managerId !== null) {
                $implicitManagerIds[(int) $managerId] = true;
            }
        }

        $accessibleProjectIds = $isManager ? [] : array_values(array_unique(array_merge(
            $projectRepository->getProjectIdsForUser($currentUserId),
            $projectAssignmentRepository->getProjectIdsByUserId($currentUserId),
        )));
        $accessibleProjectSet = array_fill_keys($accessibleProjectIds, true);

        $projects = $isManager
            ? $projectRepository->findForIndex()
            : array_values(array_filter(
                $projectRepository->findIndexedByIds($accessibleProjectIds),
                static fn (Project $project): bool => $project->getId() !== null
            ));

        $tasks = $isManager
            ? $taskRepository->findForManager()
            : $taskRepository->findForUser($currentUserId);

        $taskProjectIds = [];
        foreach ($tasks as $task) {
            $pid = (int) ($task->getProjectId() ?? 0);
            if ($pid > 0) {
                $taskProjectIds[$pid] = true;
            }
        }

        $projectsById = $projectRepository->findIndexedByIds(array_values(array_unique(array_merge(
            array_keys($accessibleProjectSet),
            array_keys($taskProjectIds)
        ))));

        $projectMemberIdsByProjectId = $projectAssignmentRepository->getUserIdsByProjectIds(array_map(
            static fn (Project $project): int => (int) ($project->getId() ?? 0),
            $projects
        ));

        $userIds = [];
        foreach ($projects as $project) {
            $createdBy = $project->getCreatedBy();
            if ($createdBy !== null) {
                $userIds[(int) $createdBy] = true;
            }

            $assignedTo = $project->getAssignedTo();
            if ($assignedTo !== null) {
                $userIds[(int) $assignedTo] = true;
            }

            $pid = (int) ($project->getId() ?? 0);
            foreach (($projectMemberIdsByProjectId[$pid] ?? []) as $uid) {
                $userIds[(int) $uid] = true;
            }
        }

        foreach ($implicitManagerIds as $managerId => $_) {
            $userIds[(int) $managerId] = true;
        }
        foreach ($tasks as $task) {
            $assignedTo = $task->getAssignedTo();
            if ($assignedTo !== null) {
                $userIds[(int) $assignedTo] = true;
            }
        }

        $usersById = $utilisateurRepository->findNonAdminIndexedByIds(array_keys($userIds));

        $formatUserName = static function (int $uid) use ($usersById): string {
            return isset($usersById[$uid])
                ? UserDisplayName::format($usersById[$uid], $uid)
                : 'Unknown user';
        };

        $formatProjectMembers = static function (Project $project) use ($projectMemberIdsByProjectId, $formatUserName, $implicitManagerIds): string {
            $pid = (int) ($project->getId() ?? 0);
            if ($pid <= 0) {
                return 'Unassigned';
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

            foreach (($projectMemberIdsByProjectId[$pid] ?? []) as $uid) {
                $memberIds[(int) $uid] = true;
            }

            foreach ($implicitManagerIds as $managerId => $_) {
                $memberIds[(int) $managerId] = true;
            }

            $names = [];
            foreach (array_keys($memberIds) as $uid) {
                $names[] = $formatUserName((int) $uid);
            }

            $names = array_values(array_unique($names));
            sort($names, SORT_NATURAL | SORT_FLAG_CASE);

            return $names !== [] ? implode(', ', $names) : 'Unassigned';
        };

        $events = [];
        foreach ($projects as $project) {
            $pid = $project->getId();
            if ($pid === null) {
                continue;
            }

            if (!$isManager && !isset($accessibleProjectSet[$pid])) {
                continue;
            }

            $projectName = trim((string) $project->getName());
            $projectName = $projectName !== '' ? $projectName : ('Project #' . $pid);
            $projectUrl = $this->generateUrl('app_project_show', ['id' => $pid, 'tab' => 'overview']);

            $startDate = $project->getStartDate();
            if ($startDate instanceof \DateTimeInterface) {
                $events[] = [
                    'title' => 'Project start: ' . $projectName,
                    'start' => $startDate->format('Y-m-d'),
                    'allDay' => true,
                    'backgroundColor' => '#0ea5e9',
                    'borderColor' => '#0ea5e9',
                    'textColor' => '#ffffff',
                    'url' => $projectUrl,
                    'extendedProps' => [
                        'kind' => 'project_start',
                        'projectName' => $projectName,
                        'projectUrl' => $projectUrl,
                        'dateLabel' => $startDate->format('M d, Y'),
                        'roleLabel' => 'Project start',
                        'assignedToName' => $formatProjectMembers($project),
                    ],
                ];
            }

            $endDate = $project->getEndDate();
            if ($endDate instanceof \DateTimeInterface) {
                $events[] = [
                    'title' => 'Project deadline: ' . $projectName,
                    'start' => $endDate->format('Y-m-d'),
                    'allDay' => true,
                    'backgroundColor' => '#ef4444',
                    'borderColor' => '#ef4444',
                    'textColor' => '#ffffff',
                    'url' => $projectUrl,
                    'extendedProps' => [
                        'kind' => 'project_deadline',
                        'projectName' => $projectName,
                        'projectUrl' => $projectUrl,
                        'dateLabel' => $endDate->format('M d, Y'),
                        'roleLabel' => 'Project deadline',
                        'assignedToName' => $formatProjectMembers($project),
                    ],
                ];
            }
        }

        foreach ($tasks as $task) {
            $pid = (int) ($task->getProjectId() ?? 0);
            if ($pid <= 0) {
                continue;
            }

            $dueDate = $task->getDueDate();
            if (!$dueDate instanceof \DateTimeInterface) {
                continue;
            }

            $taskName = trim((string) $task->getTitle());
            $taskName = $taskName !== '' ? $taskName : ('Task #' . ($task->getId() ?? 0));

            $events[] = [
                'title' => 'Task deadline: ' . $taskName,
                'start' => $dueDate->format('Y-m-d'),
                'allDay' => true,
                'backgroundColor' => '#7c3aed',
                'borderColor' => '#7c3aed',
                'textColor' => '#ffffff',
                'url' => $this->generateUrl('app_task_show', ['id' => (int) $task->getId()]),
                'extendedProps' => [
                    'kind' => 'task_deadline',
                    'taskId' => (int) $task->getId(),
                    'taskName' => $taskName,
                    'projectId' => $pid,
                    'projectName' => trim((string) ($projectsById[$pid]->getName() ?? '')) !== ''
                        ? (string) $projectsById[$pid]->getName()
                        : ('Project #' . $pid),
                    'taskUrl' => $this->generateUrl('app_task_show', ['id' => (int) $task->getId()]),
                    'dateLabel' => $dueDate->format('M d, Y'),
                    'roleLabel' => 'Task deadline',
                    'status' => (string) ($task->getStatus() ?? 'todo'),
                    'assignedToName' => $formatUserName((int) ($task->getAssignedTo() ?? 0)),
                ],
            ];
        }

        usort($events, static function (array $a, array $b): int {
            return strcmp((string) ($a['start'] ?? ''), (string) ($b['start'] ?? ''));
        });

        return $this->render('project-management/apps-calendar.html.twig', [
            'calendarEvents' => $events,
            'calendarScopeLabel' => $isManager ? 'Manager view' : 'Member view',
            'calendarVisibleProjects' => count($projects),
            'calendarVisibleTasks' => count($tasks),
        ]);
    }
}
