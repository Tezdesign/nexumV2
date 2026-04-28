<?php

namespace App\Service\Project\Calendar;

use App\Entity\Projects\Project;
use App\Repository\Projects\ProjectAssignmentRepository;
use App\Repository\Projects\ProjectRepository;
use App\Repository\Tasks\TaskRepository;
use App\Repository\UserHandling\UtilisateurRepository;
use App\Entity\UserHandling\Utilisateur;
use App\Support\UserDisplayName;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class CalendarEventProvider
{
    public function __construct(
        private readonly ProjectRepository $projectRepository,
        private readonly ProjectAssignmentRepository $projectAssignmentRepository,
        private readonly TaskRepository $taskRepository,
        private readonly UtilisateurRepository $utilisateurRepository,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly PublicHolidayProvider $publicHolidayProvider,
    ) {
    }

    /**
     * @return array{scopeLabel: string, visibleProjects: int, visibleTasks: int}
     */
    public function getSummary(int $currentUserId, bool $isManager, ?int $projectId = null): array
    {
        $scope = $this->loadScope($currentUserId, $isManager, $projectId);

        return [
            'scopeLabel' => $isManager ? 'Manager view' : 'Member view',
            'visibleProjects' => count($scope['projects']),
            'visibleTasks' => count($scope['tasks']),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getEvents(
        int $currentUserId,
        bool $isManager,
        ?\DateTimeInterface $rangeStart = null,
        ?\DateTimeInterface $rangeEnd = null,
        ?int $projectId = null,
    ): array {
        $scope = $this->loadScope($currentUserId, $isManager, $projectId);

        $usersById = $scope['usersById'];
        $projectMemberIdsByProjectId = $scope['projectMemberIdsByProjectId'];
        $implicitManagerIds = $scope['implicitManagerIds'];

        $formatUserName = static function (int $uid) use ($usersById): string {
            return isset($usersById[$uid]) && $usersById[$uid] instanceof Utilisateur
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

        foreach ($scope['projects'] as $project) {
            $pid = $project->getId();
            if ($pid === null) {
                continue;
            }

            $projectName = trim((string) $project->getName());
            $projectName = $projectName !== '' ? $projectName : ('Project #' . $pid);
            $projectUrl = $this->urlGenerator->generate('app_project_show', ['id' => $pid, 'tab' => 'overview']);

            $startDate = $project->getStartDate();
            if ($startDate instanceof \DateTimeInterface && $this->isInRange($startDate, $rangeStart, $rangeEnd)) {
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
                        'projectId' => (int) $pid,
                        'projectName' => $projectName,
                        'projectUrl' => $projectUrl,
                        'dateLabel' => $startDate->format('M d, Y'),
                        'roleLabel' => 'Project start',
                        'assignedToName' => $formatProjectMembers($project),
                    ],
                ];
            }

            $endDate = $project->getEndDate();
            if ($endDate instanceof \DateTimeInterface && $this->isInRange($endDate, $rangeStart, $rangeEnd)) {
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
                        'projectId' => (int) $pid,
                        'projectName' => $projectName,
                        'projectUrl' => $projectUrl,
                        'dateLabel' => $endDate->format('M d, Y'),
                        'roleLabel' => 'Project deadline',
                        'assignedToName' => $formatProjectMembers($project),
                    ],
                ];
            }
        }

        foreach ($scope['tasks'] as $task) {
            $pid = (int) ($task->getProjectId() ?? 0);
            if ($pid <= 0) {
                continue;
            }

            $dueDate = $task->getDueDate();
            if (!$dueDate instanceof \DateTimeInterface || !$this->isInRange($dueDate, $rangeStart, $rangeEnd)) {
                continue;
            }

            $taskName = trim((string) $task->getTitle());
            $taskName = $taskName !== '' ? $taskName : ('Task #' . ($task->getId() ?? 0));
            $taskDetails = trim((string) ($task->getDescription() ?? ''));
            $taskDetails = $taskDetails !== '' ? $taskDetails : 'No description provided.';

            $projectEntity = $scope['projectsById'][$pid] ?? null;
            $projectName = trim((string) ($projectEntity?->getName() ?? ''));
            $projectName = $projectName !== '' ? $projectName : ('Project #' . $pid);
            $taskUrl = $this->urlGenerator->generate('app_task_show', ['id' => (int) $task->getId()]);

            $events[] = [
                'title' => 'Task deadline: ' . $taskName,
                'start' => $dueDate->format('Y-m-d'),
                'allDay' => true,
                'backgroundColor' => '#7c3aed',
                'borderColor' => '#7c3aed',
                'textColor' => '#ffffff',
                'url' => $taskUrl,
                'extendedProps' => [
                    'kind' => 'task_deadline',
                    'taskId' => (int) $task->getId(),
                    'taskName' => $taskName,
                    'projectId' => $pid,
                    'projectName' => $projectName,
                    'taskUrl' => $taskUrl,
                    'dateLabel' => $dueDate->format('M d, Y'),
                    'roleLabel' => 'Task deadline',
                    'status' => (string) ($task->getStatus() ?? 'todo'),
                    'assignedToName' => $formatUserName((int) ($task->getAssignedTo() ?? 0)),
                    'details' => $taskDetails,
                ],
            ];
        }

        foreach ($this->publicHolidayProvider->getHolidays($rangeStart, $rangeEnd) as $holiday) {
            /** @var \DateTimeImmutable $holidayDate */
            $holidayDate = $holiday['date'];
            $localName = trim((string) ($holiday['localName'] ?? 'Public holiday'));
            $holidayName = trim((string) ($holiday['name'] ?? $localName));
            $displayName = $localName !== '' ? $localName : ($holidayName !== '' ? $holidayName : 'Public holiday');
            $isGlobal = (bool) ($holiday['global'] ?? false);
            $types = (array) ($holiday['types'] ?? []);

            $details = $isGlobal
                ? sprintf('Public holiday in %s.', (string) ($holiday['countryCode'] ?? $this->publicHolidayProvider->getCountryCode()))
                : sprintf('Observed holiday in %s.', (string) ($holiday['countryCode'] ?? $this->publicHolidayProvider->getCountryCode()));
            if ($types !== []) {
                $details .= ' Type: ' . implode(', ', array_map('strval', $types)) . '.';
            }

            $events[] = [
                'title' => 'Holiday: ' . $displayName,
                'start' => $holidayDate->format('Y-m-d'),
                'allDay' => true,
                'backgroundColor' => '#f59e0b',
                'borderColor' => '#f59e0b',
                'textColor' => '#1f2937',
                'extendedProps' => [
                    'kind' => 'public_holiday',
                    'dateLabel' => $holidayDate->format('M d, Y'),
                    'roleLabel' => 'Public holiday',
                    'details' => $details,
                    'countryCode' => (string) ($holiday['countryCode'] ?? $this->publicHolidayProvider->getCountryCode()),
                ],
            ];
        }

        usort($events, static function (array $a, array $b): int {
            return strcmp((string) $a['start'], (string) $b['start']);
        });

        return $events;
    }

    public function getHolidayCountryCode(): string
    {
        return $this->publicHolidayProvider->getCountryCode();
    }

    /**
     * @return array{
     *     projects: Project[],
     *     tasks: array<int, object>,
     *     projectsById: array<int, Project>,
     *     projectMemberIdsByProjectId: array<int, int[]>,
     *     usersById: array<int, object>,
     *     implicitManagerIds: array<int, bool>
     * }
     */
    private function loadScope(int $currentUserId, bool $isManager, ?int $projectId = null): array
    {
        $projectId = $projectId !== null && $projectId > 0 ? $projectId : null;

        $implicitManagerIds = [];
        foreach ($this->utilisateurRepository->findManagerUsers() as $managerUser) {
            $managerId = $managerUser->getId();
            if ($managerId !== null) {
                $implicitManagerIds[(int) $managerId] = true;
            }
        }

        $accessibleProjectIds = $isManager ? [] : array_values(array_unique(array_merge(
            $this->projectRepository->getProjectIdsForUser($currentUserId),
            $this->projectAssignmentRepository->getProjectIdsByUserId($currentUserId),
        )));
        $accessibleProjectSet = array_fill_keys($accessibleProjectIds, true);

        $projects = $isManager
            ? $this->projectRepository->findForIndex()
            : array_values(array_filter(
                $this->projectRepository->findIndexedByIds($accessibleProjectIds),
                static fn (Project $project): bool => $project->getId() !== null
            ));

        if ($projectId !== null) {
            $projects = array_values(array_filter(
                $projects,
                static fn (Project $project): bool => (int) ($project->getId() ?? 0) === $projectId
            ));
        }

        $tasks = $isManager
            ? $this->taskRepository->findForManager()
            : $this->taskRepository->findForUser($currentUserId);

        if ($projectId !== null) {
            $tasks = array_values(array_filter(
                $tasks,
                static fn ($task): bool => (int) ($task->getProjectId() ?? 0) === $projectId
            ));
        }

        $taskProjectIds = [];
        foreach ($tasks as $task) {
            $pid = (int) ($task->getProjectId() ?? 0);
            if ($pid > 0) {
                $taskProjectIds[$pid] = true;
            }
        }

        $projectsById = $this->projectRepository->findIndexedByIds(array_values(array_unique(array_merge(
            array_keys($accessibleProjectSet),
            array_keys($taskProjectIds),
            $projectId !== null ? [$projectId] : []
        ))));

        $projectMemberIdsByProjectId = $this->projectAssignmentRepository->getUserIdsByProjectIds(array_map(
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

        return [
            'projects' => $projects,
            'tasks' => $tasks,
            'projectsById' => $projectsById,
            'projectMemberIdsByProjectId' => $projectMemberIdsByProjectId,
            'usersById' => $this->utilisateurRepository->findNonAdminIndexedByIds(array_keys($userIds)),
            'implicitManagerIds' => $implicitManagerIds,
        ];
    }

    private function isInRange(
        \DateTimeInterface $date,
        ?\DateTimeInterface $rangeStart,
        ?\DateTimeInterface $rangeEnd,
    ): bool {
        $day = \DateTimeImmutable::createFromInterface($date)->setTime(0, 0);

        if ($rangeStart instanceof \DateTimeInterface) {
            $start = \DateTimeImmutable::createFromInterface($rangeStart)->setTime(0, 0);
            if ($day < $start) {
                return false;
            }
        }

        if ($rangeEnd instanceof \DateTimeInterface) {
            $end = \DateTimeImmutable::createFromInterface($rangeEnd)->setTime(0, 0);
            if ($day >= $end) {
                return false;
            }
        }

        return true;
    }
}
