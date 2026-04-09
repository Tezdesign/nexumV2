<?php

namespace App\Controller;

use App\Entity\Projects\Project;
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
    #[Route('/', name: 'dashboard')]
    public function index(
        ProjectRepository $projectRepository,
        ProjectAssignmentRepository $projectAssignmentRepository,
        TaskRepository $taskRepository,
        UtilisateurRepository $utilisateurRepository
    ): Response
    {
        $currentUser = $utilisateurRepository->findFirstManagerOrFirst();
        $currentUserId = (int) ($currentUser?->getId() ?? 0);
        $role = strtolower((string) ($currentUser?->getRole() ?? ''));
        $isManager = $role !== '' && str_contains($role, 'manager');

        $welcomeName = trim((string) ($currentUser?->getPrenom() ?? ''));
        if ($welcomeName === '') {
            $welcomeName = UserDisplayName::format($currentUser, $currentUserId);
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

        $dashboardTasks = $isManager
            ? $taskRepository->findForManager()
            : $taskRepository->findForDashboardScope($currentUserId, $relatedProjectIds);

        $yourTasks = $taskRepository->findCreatedByUser($currentUserId, 6);

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
        $firstLetter = static function (string $value): string {
            $value = trim($value);
            if ($value === '') {
                return '?';
            }

            return strtoupper(substr($value, 0, 1));
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
            $accentClass = $progress >= 75 ? 'success' : ($progress >= 35 ? 'primary' : 'secondary');

            $projectCards[] = [
                'id' => $pid,
                'name' => $projectName,
                'initial' => $firstLetter($projectName),
                'startLabel' => $formatDate($project->getStartDate()),
                'endLabel' => $formatDate($project->getEndDate()),
                'progress' => $progress,
                'accentClass' => $accentClass,
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
            $statusLabel = match (true) {
                $isCompleted($status) => 'Done',
                $isInProgress($status) => 'In Progress',
                default => 'To Do',
            };
            $statusClass = match (true) {
                $isCompleted($status) => 'success',
                $isInProgress($status) => 'primary',
                default => 'secondary',
            };

            $priority = $normalizeStatus($task->getPriority());
            $priorityLabel = $priority !== '' ? strtoupper($priority) : 'NORMAL';
            $priorityClass = match ($priority) {
                'high' => 'danger',
                'medium' => 'warning',
                'low' => 'info',
                default => 'secondary',
            };

            $taskCards[] = [
                'id' => $tid,
                'title' => trim((string) $task->getTitle()) ?: ('Task #' . $tid),
                'projectName' => $projectName,
                'statusLabel' => $statusLabel,
                'statusClass' => $statusClass,
                'priorityLabel' => $priorityLabel,
                'priorityClass' => $priorityClass,
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
        // Point the existing sidebar entry to the real Projects CRUD list.
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
        // Point the existing sidebar entry to the real Tasks board.
        return $this->redirectToRoute('app_task_index');
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
        $currentUser = $utilisateurRepository->findFirstManagerOrFirst();
        $currentUserId = (int) ($currentUser?->getId() ?? 0);
        $role = strtolower((string) ($currentUser?->getRole() ?? ''));
        $isManager = $role !== '' && str_contains($role, 'manager');

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
        foreach ($tasks as $task) {
            $assignedTo = $task->getAssignedTo();
            if ($assignedTo !== null) {
                $userIds[(int) $assignedTo] = true;
            }
        }

        $usersById = $utilisateurRepository->findIndexedByIds(array_keys($userIds));

        $formatUserName = static function (int $uid) use ($usersById): string {
            return isset($usersById[$uid])
                ? UserDisplayName::format($usersById[$uid], $uid)
                : 'Unknown user';
        };

        $formatProjectMembers = static function (Project $project) use ($projectMemberIdsByProjectId, $formatUserName): string {
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
