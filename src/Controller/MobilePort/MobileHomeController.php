<?php

namespace App\Controller\MobilePort;

use App\Controller\Project\ProjectProgressEngine;
use App\Entity\FinancialAnalysis\ExpenseDraft;
use App\Entity\Projects\Project;
use App\Form\FinancialAnalysis\ExpenseDraftType;
use App\Repository\FinancialAnalysis\ExpenseDraftRepository;
use App\Repository\Projects\ProjectAssignmentRepository;
use App\Repository\Projects\ProjectRepository;
use App\Repository\Tasks\TaskRepository;
use App\Repository\UserHandling\UtilisateurRepository;
use App\Service\AuthService;
use App\Service\ProjectActivityFeed;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class MobileHomeController extends AbstractController
{
    private AuthService $authService;

    public function __construct(AuthService $authService)
    {
        $this->authService = $authService;
    }

    #[Route('/mobile', name: 'app_mobile_dashboard')]
    public function index(
        ProjectRepository $projectRepository,
        ProjectAssignmentRepository $projectAssignmentRepository,
        TaskRepository $taskRepository
    ): Response {
        if (!$this->authService->isLoggedIn()) {
            return $this->redirectToRoute('app_mobile_login');
        }

        $role = strtolower((string) ($this->authService->getCurrentUserRole() ?? ''));
        if (!in_array($role, ['project manager', 'employee', 'manager'])) {
            return $this->redirectToRoute('app_mobile_login');
        }

        $currentUserId = (int) ($this->authService->getCurrentUserId() ?? 0);
        $isManager = $this->authService->isManager();

        $relatedProjectIds = $isManager ? [] : array_values(array_unique(array_merge(
            $projectRepository->getProjectIdsForUser($currentUserId),
            $projectAssignmentRepository->getProjectIdsByUserId($currentUserId),
        )));

        $projects = $isManager
            ? $projectRepository->findForIndex()
            : array_values($projectRepository->findIndexedByIds($relatedProjectIds));

        $projectIds = array_filter(array_map(fn($p) => $p->getId(), $projects));
        $projectProgressById = $taskRepository->getProgressPercentByProjectIds($projectIds);

        $mobileProjects = [];
        foreach ($projects as $project) {
            if ($project->getId() === null) continue;

            $mobileProjects[] = [
                'id' => $project->getId(),
                'name' => trim((string) $project->getName()) ?: ('Project #' . $project->getId()),
                'progress' => $projectProgressById[$project->getId()] ?? 0,
            ];
        }

        return $this->render('mobile/dashboard.html.twig', [
            'projects' => $mobileProjects,
            'role' => $role
        ]);
    }

    #[Route('/mobile/project/{id}', name: 'app_mobile_project_show', methods: ['GET', 'POST'])]
    public function show(
        Request $request,
        Project $project,
        ProjectRepository $projectRepository,
        ProjectAssignmentRepository $projectAssignmentRepository,
        ProjectActivityFeed $activityFeed,
        UtilisateurRepository $utilisateurRepository,
        TaskRepository $taskRepository,
        ExpenseDraftRepository $expenseDraftRepository,
        \App\Service\FinancialAnalysis\BudgetAdvService $budgetAdvService,
        EntityManagerInterface $entityManager
    ): Response {
        if (!$this->authService->isLoggedIn()) {
            return $this->redirectToRoute('app_mobile_login');
        }

        $currentUserId = (int) ($this->authService->getCurrentUserId() ?? 0);
        $currentUser = $currentUserId > 0 ? $utilisateurRepository->find($currentUserId) : null;
        $isManager = $this->authService->isManager();

        // Security check
        if (!$isManager) {
            $visibleProjectIds = array_fill_keys(array_unique(array_merge(
                $projectRepository->getProjectIdsForUser($currentUserId),
                $projectAssignmentRepository->getProjectIdsByUserId($currentUserId)
            )), true);

            if ($project->getId() === null || !isset($visibleProjectIds[(int) $project->getId()])) {
                throw $this->createNotFoundException();
            }
        }

        $pid = $project->getId();
        $tab = strtolower(trim((string) $request->query->get('tab', 'overview')));
        $allowedTabs = ['overview', 'tasks', 'drafts'];
        if (!in_array($tab, $allowedTabs, true)) {
            $tab = 'overview';
        }

        // Fetch team members for the project
        $teamMemberIds = [];
        foreach ($utilisateurRepository->findManagerUsers() as $managerUser) {
            $managerId = $managerUser->getId();
            if ($managerId !== null) {
                $teamMemberIds[(int) $managerId] = true;
            }
        }
        if ($project->getCreatedBy() !== null) {
            $teamMemberIds[$project->getCreatedBy()] = true;
        }
        if ($project->getAssignedTo() !== null) {
            $teamMemberIds[$project->getAssignedTo()] = true;
        }
        if ($pid !== null) {
            foreach ($projectAssignmentRepository->getUserIdsByProjectId((int) $pid) as $uid) {
                $teamMemberIds[(int) $uid] = true;
            }
        }

        // Stats and Progress
        $projectTasks = $pid !== null ? $taskRepository->findForProject((int) $pid) : [];
        $projectOverview = ProjectProgressEngine::build($project, $projectTasks);
        $projectProgressPercent = (int) ($projectOverview['completion_percentage'] ?? 0);
        $statusCounts = (array) ($projectOverview['status_counts'] ?? []);
        $stats = [
            'total' => (int) ($projectOverview['tasks_total'] ?? 0),
            'completed' => (int) ($statusCounts['done'] ?? 0),
            'overdue' => (int) ($projectOverview['tasks_overdue'] ?? 0),
        ];

        // Drafts Logic (same as desktop)
        $drafts = $expenseDraftRepository->findByProject($project);
        $createDraftForm = null;

        if ($isManager) {
            $newDraft = new ExpenseDraft();
            $draftForm = $this->createForm(ExpenseDraftType::class, $newDraft, [
                'project_id' => $pid,
            ]);

            $draftForm->handleRequest($request);

            if ($draftForm->isSubmitted() && $draftForm->isValid()) {
                if ($currentUser) {
                    $newDraft->setCreatedBy($currentUser);
                }
                
                $budgetAdvService->evaluateDraft($newDraft);

                return $this->redirectToRoute('app_mobile_project_show', ['id' => $pid, 'tab' => 'drafts']);
            }

            $createDraftForm = $draftForm->createView();
        }

        // Avatars and Names for tabs
        $allUserIds = array_map('intval', array_keys($teamMemberIds));
        $membersById = $utilisateurRepository->findNonAdminIndexedByIds($allUserIds);
        $avatarUrlById = [];
        foreach ($membersById as $uid => $user) {
            $raw = $user->getImagelink();
            if (is_string($raw) && trim($raw) !== '' && preg_match('~^(https?://|/|data:image/)~', trim($raw)) === 1) {
                $avatarUrlById[(int) $uid] = trim($raw);
            }
        }

        return $this->render('mobile/project_show.html.twig', [
            'project' => $project,
            'isManager' => $isManager,
            'currentUserId' => $currentUserId,
            'membersById' => $membersById,
            'avatarUrlById' => $avatarUrlById,
            'stats' => $stats,
            'projectOverview' => $projectOverview,
            'tasks' => $projectTasks,
            'drafts' => $drafts,
            'activeTab' => $tab,
            'createDraftForm' => $createDraftForm,
            'projectProgressPercent' => $projectProgressPercent,
            'recentActivities' => $pid !== null ? $activityFeed->findRecentForProject((int) $pid, 6) : [],
            'memberIds' => array_keys($teamMemberIds),
        ]);
    }

    #[Route('/mobile/profile', name: 'app_mobile_profile', methods: ['GET'])]
    public function profile(UtilisateurRepository $utilisateurRepository): Response
    {
        if (!$this->authService->isLoggedIn()) {
            return $this->redirectToRoute('app_mobile_login');
        }

        $userId = $this->authService->getCurrentUserId();
        $utilisateur = $utilisateurRepository->find($userId);

        if (!$utilisateur) {
            $this->authService->logout();
            return $this->redirectToRoute('app_mobile_login');
        }

        return $this->render('mobile/profile/settings.html.twig', [
            'utilisateur' => $utilisateur,
        ]);
    }
}
