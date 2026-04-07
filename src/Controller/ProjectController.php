<?php

namespace App\Controller;

use App\Entity\Projects\Project;
use App\Entity\Projects\ProjectAssignment;
use App\Form\Projects\ProjectQuickCreateType;
use App\Form\Projects\ProjectType;
use App\Repository\Projects\ProjectAssignmentRepository;
use App\Repository\Projects\ProjectRepository;
use App\Repository\Tasks\TaskRepository;
use App\Repository\UserHandling\UtilisateurRepository;
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
        UtilisateurRepository $utilisateurRepository,
        EntityManagerInterface $entityManager
    ): Response
    {
        $q = trim((string) $request->query->get('q', ''));

        // Modal "quick create" form (same page, no navigation).
        $createProject = new Project();
        $createForm = $this->createForm(ProjectQuickCreateType::class, $createProject, [
            'action' => $this->generateUrl('app_project_index'),
            'method' => 'POST',
        ]);
        $createForm->handleRequest($request);

        if ($createForm->isSubmitted() && $createForm->isValid()) {
            // Set legacy required field(s) that shouldn't be user-editable in the modal.
            $manager = $utilisateurRepository->findFirstManagerOrFirst();
            $createProject->setCreatedBy($manager?->getId() ?? 1);

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
                $entityManager->flush();
            }

            return $this->redirectToRoute('app_project_index', [], Response::HTTP_SEE_OTHER);
        }

        $projects = $projectRepository->findForIndex($q);

        $projectIds = [];
        foreach ($projects as $project) {
            if ($project->getId() !== null) {
                $projectIds[] = $project->getId();
            }
        }

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

            foreach ($memberIdsByProjectId[$pid] as $uid) {
                $userIds[$uid] = true;
            }
        }

        $usersById = $utilisateurRepository->findIndexedByIds(array_keys($userIds));

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

        // Data for the styled "Assigned to" dropdown in the create modal.
        $assignableUsers = [];
        $users = $utilisateurRepository->createQueryBuilder('u')
            ->orderBy('u.role', 'ASC')
            ->addOrderBy('u.prenom', 'ASC')
            ->addOrderBy('u.nom', 'ASC')
            ->getQuery()
            ->getResult();

        foreach ($users as $u) {
            $fullName = trim(((string) $u->getPrenom()) . ' ' . ((string) $u->getNom()));
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
                'name' => $fullName !== '' ? $fullName : ('User #' . $u->getId()),
                'role' => $role,
                'img' => $img,
            ];
        }

        return $this->render('project/index.html.twig', [
            'projects' => $projects,
            'q' => $q,
            'usersById' => $usersById,
            'avatarUrlById' => $avatarUrlById,
            'memberIdsByProjectId' => $memberIdsByProjectId,
            'createForm' => $createForm->createView(),
            'assignableUsers' => $assignableUsers,
        ]);
    }

    #[Route('/new', name: 'app_project_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $project = new Project();
        $form = $this->createForm(ProjectType::class, $project);
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
        ProjectAssignmentRepository $projectAssignmentRepository,
        UtilisateurRepository $utilisateurRepository,
        TaskRepository $taskRepository
    ): Response
    {
        $pid = $project->getId();

        $tab = strtolower(trim((string) $request->query->get('tab', 'overview')));
        $allowedTabs = ['overview', 'tasks', 'kanban', 'discussion', 'files', 'activity', 'settings'];
        if (!in_array($tab, $allowedTabs, true)) {
            $tab = 'overview';
        }

        $teamMemberIds = [];
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

        $stats = ['total' => 0, 'completed' => 0, 'overdue' => 0];
        if ($pid !== null) {
            $stats = $taskRepository->getStatsForProject((int) $pid);
        }

        $tasks = [];
        $taskUserIds = [];
        if ($pid !== null && $tab === 'tasks') {
            $tasks = $taskRepository->findForProject((int) $pid, 200);
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

        $membersById = $utilisateurRepository->findIndexedByIds($allUserIds);
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

        return $this->render('project/show.html.twig', [
            'project' => $project,
            'memberIds' => $memberIds,
            'membersById' => $membersById,
            'avatarUrlById' => $avatarUrlById,
            'stats' => $stats,
            'tasks' => $tasks,
            'activeTab' => $tab,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_project_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Project $project, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(ProjectType::class, $project);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            return $this->redirectToRoute('app_project_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('project/edit.html.twig', [
            'project' => $project,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_project_delete', methods: ['POST'])]
    public function delete(Request $request, Project $project, TaskRepository $taskRepository, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$project->getId(), (string) $request->request->get('_token', ''))) {
            $pid = $project->getId();
            if ($pid !== null) {
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
}
