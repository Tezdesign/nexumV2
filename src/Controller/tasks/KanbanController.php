<?php

namespace App\Controller\tasks;

use App\Entity\Projects\Project;
use App\Repository\Projects\ProjectAssignmentRepository;
use App\Repository\Projects\ProjectRepository;
use App\Repository\Tasks\TaskRepository;
use App\Repository\UserHandling\UtilisateurRepository;
use App\Service\AuthService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

final class KanbanController extends AbstractController
{
    public function __construct(private readonly AuthService $authService)
    {
    }

    #[Route('/apps-kanban', name: 'apps-kanban')]
    public function index(
        Request $request,
        ProjectRepository $projectRepository,
        ProjectAssignmentRepository $projectAssignmentRepository,
        TaskRepository $taskRepository,
        UtilisateurRepository $utilisateurRepository
    ): Response
    {
        if (!$this->authService->isLoggedIn()) {
            return $this->redirectToRoute('welcome');
        }

        $currentUserId = (int) ($this->authService->getCurrentUserId() ?? 0);
        $isManager = $this->authService->isManager();

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

        if ($isManager) {
            $projectChoices = [];
            foreach ($projectRepository->findForIndex() as $project) {
                $pid = $project->getId();
                if ($pid !== null) {
                    $projectChoices[(int) $pid] = $project;
                }
            }
        } else {
            $relatedProjectIds = array_values(array_unique(array_merge(
                $projectRepository->getProjectIdsForUser($currentUserId),
                $projectAssignmentRepository->getProjectIdsByUserId($currentUserId),
                array_keys($taskProjectIds)
            )));
            $projectChoices = $projectRepository->findIndexedByIds($relatedProjectIds);
        }

        uasort($projectChoices, static function (Project $left, Project $right): int {
            return strcasecmp(trim((string) $left->getName()), trim((string) $right->getName()));
        });

        $selectedProjectId = (int) $request->query->get('project', 0);
        if ($selectedProjectId > 0 && !isset($projectChoices[$selectedProjectId])) {
            $selectedProjectId = 0;
        }
        if (!$isManager && $selectedProjectId <= 0 && $projectChoices !== []) {
            $selectedProjectId = (int) array_key_first($projectChoices);
        }

        if ($selectedProjectId > 0) {
            $tasks = array_values(array_filter(
                $tasks,
                static fn (\App\Entity\Tasks\Task $task): bool => (int) ($task->getProjectId() ?? 0) === $selectedProjectId
            ));
        }

        $projectsById = $projectRepository->findIndexedByIds(array_values(array_unique(array_merge(
            array_keys($projectChoices),
            array_keys($taskProjectIds)
        ))));

        $tasks = array_values(array_filter(
            $tasks,
            static fn (\App\Entity\Tasks\Task $task): bool => isset($projectsById[(int) ($task->getProjectId() ?? 0)])
        ));

        $userIds = [];
        foreach ($tasks as $task) {
            $assignedTo = (int) ($task->getAssignedTo() ?? 0);
            if ($assignedTo > 0) {
                $userIds[$assignedTo] = true;
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

        $normalizeStatus = static function (?string $status): string {
            return strtolower(trim((string) $status));
        };
        $isCompleted = static function (?string $status) use ($normalizeStatus): bool {
            return in_array($normalizeStatus($status), ['done', 'completed', 'complete', 'finished'], true);
        };
        $isInProgress = static function (?string $status) use ($normalizeStatus): bool {
            return in_array($normalizeStatus($status), ['in_progress', 'in progress', 'progress', 'doing', 'started'], true);
        };

        $columns = [
            ['key' => 'todo', 'label' => 'To Do', 'tasks' => []],
            ['key' => 'in_progress', 'label' => 'In Progress', 'tasks' => []],
            ['key' => 'done', 'label' => 'Done', 'tasks' => []],
        ];

        foreach ($tasks as $task) {
            $status = $normalizeStatus($task->getStatus());
            $columnIndex = match (true) {
                $isCompleted($status) => 2,
                $isInProgress($status) => 1,
                default => 0,
            };
            $columns[$columnIndex]['tasks'][] = $task;
        }

        $selectedProjectName = '';
        if ($selectedProjectId > 0 && isset($projectChoices[$selectedProjectId])) {
            $selectedProjectName = trim((string) $projectChoices[$selectedProjectId]->getName());
        }

        $createTaskParams = ['create' => 1];
        if ($selectedProjectId > 0) {
            $createTaskParams['project'] = $selectedProjectId;
        }

        return $this->render('project-management/apps-kanban.html.twig', [
            'isManager' => $isManager,
            'currentUserId' => $currentUserId,
            'columns' => $columns,
            'projectsById' => $projectsById,
            'projectChoices' => $projectChoices,
            'selectedProjectId' => $selectedProjectId,
            'selectedProjectName' => $selectedProjectName,
            'usersById' => $usersById,
            'avatarUrlById' => $avatarUrlById,
            'createTaskUrl' => $this->generateUrl('app_task_index', $createTaskParams),
            'canCreateTask' => $isManager || $projectChoices !== [],
            'showAllOption' => $isManager,
            'allProjectsLabel' => 'All projects',
        ]);
    }
}
