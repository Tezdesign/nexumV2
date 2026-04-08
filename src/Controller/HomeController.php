<?php

namespace App\Controller;

use App\Entity\Projects\Project;
use App\Entity\Tasks\Task;
use App\Repository\Projects\ProjectAssignmentRepository;
use App\Repository\Projects\ProjectRepository;
use App\Repository\Tasks\TaskRepository;
use App\Repository\UserHandling\UtilisateurRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class HomeController extends AbstractController
{
    #[Route('/', name: 'dashboard')]
    public function index(): Response
    {
        return $this->render('index.html.twig');
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

        $projectsById = [];
        foreach ($projects as $project) {
            if ($project->getId() !== null) {
                $projectsById[(int) $project->getId()] = $project;
            }
        }

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
                    'classNames' => ['bg-info'],
                    'url' => $projectUrl,
                    'extendedProps' => [
                        'kind' => 'project_start',
                        'projectName' => $projectName,
                        'projectUrl' => $projectUrl,
                        'dateLabel' => $startDate->format('M d, Y'),
                        'roleLabel' => 'Project start',
                    ],
                ];
            }

            $endDate = $project->getEndDate();
            if ($endDate instanceof \DateTimeInterface) {
                $events[] = [
                    'title' => 'Project deadline: ' . $projectName,
                    'start' => $endDate->format('Y-m-d'),
                    'allDay' => true,
                    'classNames' => ['bg-danger'],
                    'url' => $projectUrl,
                    'extendedProps' => [
                        'kind' => 'project_deadline',
                        'projectName' => $projectName,
                        'projectUrl' => $projectUrl,
                        'dateLabel' => $endDate->format('M d, Y'),
                        'roleLabel' => 'Project deadline',
                    ],
                ];
            }
        }

        foreach ($tasks as $task) {
            $pid = (int) ($task->getProjectId() ?? 0);
            if ($pid <= 0) {
                continue;
            }

            if (!$isManager && !isset($accessibleProjectSet[$pid])) {
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
                'classNames' => ['bg-primary'],
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
