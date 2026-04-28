<?php

namespace App\Twig\Components;

use App\Entity\Tasks\Task;
use App\Repository\Projects\ProjectRepository;
use App\Repository\Tasks\TaskRepository;
use App\Repository\UserHandling\UtilisateurRepository;
use App\Service\AuthService;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\DefaultActionTrait;

#[AsLiveComponent]
final class TaskBoard
{
    use DefaultActionTrait;

    #[LiveProp(writable: true)]
    public string $q = '';

    #[LiveProp(writable: true)]
    public string $statusFilter = '';

    #[LiveProp(writable: true)]
    public string $priorityFilter = '';

    #[LiveProp]
    public int $prefillProjectId = 0;

    #[LiveProp]
    public bool $canCreateTask = false;

    /**
     * @var array<string, mixed>|null
     */
    private ?array $boardData = null;

    public function __construct(
        private readonly TaskRepository $taskRepository,
        private readonly ProjectRepository $projectRepository,
        private readonly UtilisateurRepository $utilisateurRepository,
        private readonly AuthService $authService,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    #[LiveAction]
    public function resetFilters(): void
    {
        $this->q = '';
        $this->statusFilter = '';
        $this->priorityFilter = '';
    }

    /**
     * @return array<int, Task[]>
     */
    public function getTasksByProjectId(): array
    {
        return $this->getBoardData()['tasksByProjectId'];
    }

    /**
     * @return array<int, mixed>
     */
    public function getProjectsById(): array
    {
        return $this->getBoardData()['projectsById'];
    }

    /**
     * @return array<int, mixed>
     */
    public function getUsersById(): array
    {
        return $this->getBoardData()['usersById'];
    }

    /**
     * @return array<int, string>
     */
    public function getAvatarUrlById(): array
    {
        return $this->getBoardData()['avatarUrlById'];
    }

    public function getCurrentUserId(): int
    {
        return $this->getBoardData()['currentUserId'];
    }

    public function isManager(): bool
    {
        return $this->getBoardData()['isManager'];
    }

    public function getTaskIndexUrl(): string
    {
        $params = [];

        if ($this->q !== '') {
            $params['q'] = $this->q;
        }
        if ($this->statusFilter !== '') {
            $params['status'] = $this->statusFilter;
        }
        if ($this->priorityFilter !== '') {
            $params['priority'] = $this->priorityFilter;
        }
        if ($this->prefillProjectId > 0) {
            $params['project'] = $this->prefillProjectId;
        }

        return $this->urlGenerator->generate('app_task_index', $params);
    }

    /**
     * @return array<string, mixed>
     */
    private function getBoardData(): array
    {
        if ($this->boardData !== null) {
            return $this->boardData;
        }

        $currentUserId = (int) ($this->authService->getCurrentUserId() ?? 0);
        $isManager = $this->authService->isManager();

        $q = trim($this->q);
        $statusFilter = strtolower(trim($this->statusFilter));
        $priorityFilter = strtolower(trim($this->priorityFilter));

        $statusFilter = in_array($statusFilter, ['todo', 'in_progress', 'done'], true) ? $statusFilter : '';
        $priorityFilter = in_array($priorityFilter, ['high', 'medium', 'low'], true) ? $priorityFilter : '';

        $tasks = $isManager
            ? $this->taskRepository->findForManager($q !== '' ? $q : null, $statusFilter !== '' ? $statusFilter : null, $priorityFilter !== '' ? $priorityFilter : null)
            : $this->taskRepository->findForUser($currentUserId, $q !== '' ? $q : null, $statusFilter !== '' ? $statusFilter : null, $priorityFilter !== '' ? $priorityFilter : null);

        if ($this->prefillProjectId > 0) {
            $tasks = array_values(array_filter(
                $tasks,
                fn (Task $task): bool => (int) ($task->getProjectId() ?? 0) === $this->prefillProjectId
            ));
        }

        $projectIds = [];
        $userIds = [];
        foreach ($tasks as $task) {
            $projectId = $task->getProjectId();
            if ($projectId !== null) {
                $projectIds[$projectId] = true;
            }

            $assignedTo = $task->getAssignedTo();
            if ($assignedTo !== null) {
                $userIds[$assignedTo] = true;
            }
        }

        $projectsById = $this->projectRepository->findIndexedByIds(array_keys($projectIds));
        $usersById = $this->utilisateurRepository->findNonAdminIndexedByIds(array_keys($userIds));

        $tasks = array_values(array_filter(
            $tasks,
            static fn (Task $task): bool => isset($projectsById[(int) ($task->getProjectId() ?? 0)])
        ));

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

        $tasksByProjectId = [];
        foreach ($tasks as $task) {
            $projectId = $task->getProjectId() ?? 0;
            $tasksByProjectId[$projectId] ??= [];
            $tasksByProjectId[$projectId][] = $task;
        }

        $this->boardData = [
            'tasksByProjectId' => $tasksByProjectId,
            'projectsById' => $projectsById,
            'usersById' => $usersById,
            'avatarUrlById' => $avatarUrlById,
            'currentUserId' => $currentUserId,
            'isManager' => $isManager,
        ];

        return $this->boardData;
    }
}
