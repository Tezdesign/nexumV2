<?php

namespace App\Controller;

use App\Attribute\RequireLogin;
use App\Entity\Projects\Project;
use App\Entity\Tasks\Task;
use App\Repository\Projects\ProjectAssignmentRepository;
use App\Repository\Projects\ProjectRepository;
use App\Repository\Tasks\TaskRepository;
use App\Repository\UserHandling\UtilisateurRepository;
use App\Service\AuthService;
use App\Service\Project\AI\RapportAiManager;
use App\Support\UserDisplayName;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/rapport', name: 'app_rapport_')]
#[RequireLogin]
final class RapportController extends AbstractController
{
    #[Route('/{projetId}', name: 'index', methods: ['GET'])]
    public function index(
        int $projetId,
        ProjectRepository $projectRepository,
        ProjectAssignmentRepository $projectAssignmentRepository,
        TaskRepository $taskRepository,
        UtilisateurRepository $utilisateurRepository,
        AuthService $authService,
        RapportAiManager $rapportAiManager,
    ): Response {
        [$project, $mappedProject, $mappedTasks] = $this->loadRapportData(
            $projetId,
            $projectRepository,
            $projectAssignmentRepository,
            $taskRepository,
            $utilisateurRepository,
            $authService
        );

        return $this->render('project/rapport/index.html.twig', [
            'project' => $project,
            'rapportProject' => $mappedProject,
            'rapportTasks' => $mappedTasks,
            'rapportBackendStatus' => $rapportAiManager->getBackendStatus(),
        ]);
    }

    #[Route('/{projetId}/stream', name: 'stream', methods: ['GET'])]
    public function streamRapport(
        int $projetId,
        ProjectRepository $projectRepository,
        ProjectAssignmentRepository $projectAssignmentRepository,
        TaskRepository $taskRepository,
        UtilisateurRepository $utilisateurRepository,
        AuthService $authService,
        RapportAiManager $rapportAiManager,
    ): StreamedResponse {
        [, $mappedProject, $mappedTasks] = $this->loadRapportData(
            $projetId,
            $projectRepository,
            $projectAssignmentRepository,
            $taskRepository,
            $utilisateurRepository,
            $authService
        );

        $backendStatus = $rapportAiManager->getBackendStatus();
        if (!$backendStatus['available']) {
            return new StreamedResponse(function () use ($backendStatus): void {
                echo "event: server-error\n";
                echo 'data: ' . json_encode(['message' => $backendStatus['message']], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n\n";
                flush();
            }, Response::HTTP_SERVICE_UNAVAILABLE, [
                'Content-Type' => 'text/event-stream; charset=utf-8',
                'Cache-Control' => 'no-cache, no-transform',
                'X-Accel-Buffering' => 'no',
            ]);
        }

        return $rapportAiManager->streamerRapport($mappedProject, $mappedTasks);
    }

    #[Route('/{projetId}/export', name: 'export', methods: ['GET'])]
    public function export(
        int $projetId,
        ProjectRepository $projectRepository,
        ProjectAssignmentRepository $projectAssignmentRepository,
        TaskRepository $taskRepository,
        UtilisateurRepository $utilisateurRepository,
        AuthService $authService,
        RapportAiManager $rapportAiManager,
    ): Response {
        [, $mappedProject, $mappedTasks] = $this->loadRapportData(
            $projetId,
            $projectRepository,
            $projectAssignmentRepository,
            $taskRepository,
            $utilisateurRepository,
            $authService
        );

        $backendStatus = $rapportAiManager->getBackendStatus();
        if (!$backendStatus['available']) {
            return new Response($backendStatus['message'], Response::HTTP_SERVICE_UNAVAILABLE, [
                'Content-Type' => 'text/plain; charset=utf-8',
            ]);
        }

        try {
            $rapport = $rapportAiManager->genererRapport($mappedProject, $mappedTasks);
        } catch (\RuntimeException $exception) {
            return new Response($exception->getMessage(), Response::HTTP_SERVICE_UNAVAILABLE, [
                'Content-Type' => 'text/plain; charset=utf-8',
            ]);
        }

        $filename = sprintf('rapport-projet-%d.txt', (int) ($mappedProject['id'] ?? $projetId));

        return new Response($rapport, Response::HTTP_OK, [
            'Content-Type' => 'text/plain; charset=utf-8',
            'Content-Disposition' => sprintf('attachment; filename="%s"', $filename),
        ]);
    }

    /**
     * @return array{0: Project, 1: array<string, mixed>, 2: array<int, array<string, mixed>>}
     */
    private function loadRapportData(
        int $projectId,
        ProjectRepository $projectRepository,
        ProjectAssignmentRepository $projectAssignmentRepository,
        TaskRepository $taskRepository,
        UtilisateurRepository $utilisateurRepository,
        AuthService $authService,
    ): array {
        $currentUserId = (int) ($authService->getCurrentUserId() ?? 0);
        if ($currentUserId <= 0) {
            throw $this->createAccessDeniedException();
        }

        $project = $projectRepository->find($projectId);
        if (!$project instanceof Project) {
            throw $this->createNotFoundException();
        }

        if (!$authService->isManager()) {
            $visibleProjectIds = array_fill_keys(
                $this->getVisibleProjectIdsForUser($currentUserId, $projectRepository, $projectAssignmentRepository),
                true
            );

            if ($project->getId() === null || !isset($visibleProjectIds[(int) $project->getId()])) {
                throw $this->createNotFoundException();
            }
        }

        $tasks = $taskRepository->findForProject($projectId);
        $memberIds = array_fill_keys($projectAssignmentRepository->getUserIdsByProjectId($projectId), true);
        $userIds = [];

        $createdBy = $project->getCreatedBy();
        if ($createdBy !== null) {
            $userIds[(int) $createdBy] = true;
        }

        $assignedTo = $project->getAssignedTo();
        if ($assignedTo !== null) {
            $userIds[(int) $assignedTo] = true;
            $memberIds[(int) $assignedTo] = true;
        }

        foreach ($tasks as $task) {
            $taskAssignedTo = $task->getAssignedTo();
            if ($taskAssignedTo !== null) {
                $userIds[(int) $taskAssignedTo] = true;
            }

            $taskCreatedBy = $task->getCreatedBy();
            if ($taskCreatedBy !== null) {
                $userIds[(int) $taskCreatedBy] = true;
            }
        }

        foreach (array_keys($memberIds) as $memberId) {
            $userIds[(int) $memberId] = true;
        }

        $usersById = $utilisateurRepository->findNonAdminIndexedByIds(array_keys($userIds));
        $mappedProject = $this->mapProject($project, $usersById, $tasks, array_map('intval', array_keys($memberIds)));
        $mappedTasks = $this->mapTasks($tasks, $usersById);

        return [$project, $mappedProject, $mappedTasks];
    }

    /**
     * @param array<int, \App\Entity\UserHandling\Utilisateur> $usersById
     * @param Task[] $tasks
     * @param int[] $memberIds
     *
     * @return array<string, mixed>
     */
    private function mapProject(Project $project, array $usersById, array $tasks, array $memberIds): array
    {
        $today = new \DateTimeImmutable('today');
        $endDate = $project->getEndDate();
        $progress = max(0, min(100, (int) ($project->getProgress() ?? 0)));

        $completed = 0;
        $overdue = 0;
        foreach ($tasks as $task) {
            $status = strtolower(trim((string) $task->getStatus()));
            $dueDate = $task->getDueDate();
            if (in_array($status, ['done', 'completed', 'complete', 'finished'], true)) {
                ++$completed;
                continue;
            }

            if ($dueDate instanceof \DateTimeInterface && $dueDate < $today) {
                ++$overdue;
            }
        }

        if ($progress >= 100) {
            $statut = 'Termine';
        } elseif ($endDate instanceof \DateTimeInterface && $endDate < $today) {
            $statut = 'En retard';
        } elseif ($project->getStartDate() instanceof \DateTimeInterface && $project->getStartDate() > $today) {
            $statut = 'A venir';
        } else {
            $statut = 'En cours';
        }

        $responsableUser = null;
        $responsableId = $project->getAssignedTo() ?? $project->getCreatedBy();
        if ($responsableId !== null) {
            $responsableUser = $usersById[(int) $responsableId] ?? null;
        }

        $memberNames = [];
        foreach ($memberIds as $memberId) {
            if (!isset($usersById[(int) $memberId])) {
                continue;
            }

            $memberNames[(int) $memberId] = UserDisplayName::format($usersById[(int) $memberId], (int) $memberId);
        }

        if ($responsableId !== null && $responsableUser !== null) {
            $memberNames[(int) $responsableId] = UserDisplayName::format($responsableUser, $responsableId);
        }

        $memberNames = array_values($memberNames);

        return [
            'id' => $project->getId(),
            'nom' => (string) ($project->getName() ?? 'Projet sans nom'),
            'description' => trim((string) ($project->getDescription() ?? '')) ?: 'Aucune description fournie.',
            'date_debut' => $project->getStartDate()?->format('Y-m-d') ?? 'Non definie',
            'date_fin' => $project->getEndDate()?->format('Y-m-d') ?? 'Non definie',
            'responsable' => $responsableUser !== null
                ? UserDisplayName::format($responsableUser, $responsableId)
                : 'Non attribue',
            'membres' => $memberNames,
            'statut' => $statut,
            'budget' => $project->getBudget() !== null ? (string) $project->getBudget() : 'Non defini',
            'progression' => $progress,
            'stats' => [
                'total' => count($tasks),
                'completed' => $completed,
                'overdue' => $overdue,
            ],
        ];
    }

    /**
     * @param Task[] $tasks
     * @param array<int, \App\Entity\UserHandling\Utilisateur> $usersById
     *
     * @return array<int, array<string, mixed>>
     */
    private function mapTasks(array $tasks, array $usersById): array
    {
        $mapped = [];

        foreach ($tasks as $task) {
            $assignedToId = $task->getAssignedTo();
            $createdById = $task->getCreatedBy();

            $mapped[] = [
                'id' => $task->getId(),
                'titre' => (string) ($task->getTitle() ?? 'Tache sans titre'),
                'description' => trim((string) ($task->getDescription() ?? '')) ?: 'Aucune description fournie.',
                'statut' => trim((string) ($task->getStatus() ?? 'todo')) ?: 'todo',
                'priorite' => trim((string) ($task->getPriority() ?? 'medium')) ?: 'medium',
                'assigne_a' => $assignedToId !== null && isset($usersById[(int) $assignedToId])
                    ? UserDisplayName::format($usersById[(int) $assignedToId], $assignedToId)
                    : 'Non attribue',
                'cree_par' => $createdById !== null && isset($usersById[(int) $createdById])
                    ? UserDisplayName::format($usersById[(int) $createdById], $createdById)
                    : 'Inconnu',
                'echeance' => $task->getDueDate()?->format('Y-m-d') ?? 'Non definie',
            ];
        }

        return $mapped;
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
