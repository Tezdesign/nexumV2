<?php

namespace App\Controller;

use App\Entity\Tasks\Task;
use App\Form\Tasks\TaskQuickCreateType;
use App\Form\Tasks\TaskType;
use App\Repository\Projects\ProjectRepository;
use App\Repository\Tasks\TaskRepository;
use App\Repository\UserHandling\UtilisateurRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/task')]
final class TaskController extends AbstractController
{
    #[Route(name: 'app_task_index', methods: ['GET', 'POST'])]
    public function index(
        Request $request,
        TaskRepository $taskRepository,
        ProjectRepository $projectRepository,
        UtilisateurRepository $utilisateurRepository,
        EntityManagerInterface $entityManager
    ): Response
    {
        $q = trim((string) $request->query->get('q', ''));

        $currentUser = $utilisateurRepository->findFirstManagerOrFirst();
        $currentUserId = $currentUser?->getId() ?? 1;

        // Modal "quick create" form.
        $createTask = new Task();
        $createForm = $this->createForm(TaskQuickCreateType::class, $createTask, [
            'action' => $this->generateUrl('app_task_index'),
            'method' => 'POST',
        ]);
        $createForm->handleRequest($request);

        if ($createForm->isSubmitted() && $createForm->isValid()) {
            /** @var \App\Entity\Projects\Project|null $project */
            $project = $createForm->get('project')->getData();
            if ($project === null || $project->getId() === null) {
                // Shouldn't happen because "project" is required, but stay defensive.
                return $this->redirectToRoute('app_task_index', [], Response::HTTP_SEE_OTHER);
            }

            $createTask->setProjectId((int) $project->getId());
            $createTask->setAssignedTo((int) $currentUserId);
            $createTask->setCreatedBy((int) $currentUserId);
            $createTask->setCreatedAt(new \DateTime());

            $entityManager->persist($createTask);
            $entityManager->flush();

            return $this->redirectToRoute('app_task_index', [], Response::HTTP_SEE_OTHER);
        }

        $tasks = $taskRepository->findForUser((int) $currentUserId, $q !== '' ? $q : null);

        $projectIds = [];
        $userIds = [];
        foreach ($tasks as $t) {
            $pid = $t->getProjectId();
            if ($pid !== null) {
                $projectIds[$pid] = true;
            }

            $au = $t->getAssignedTo();
            if ($au !== null) {
                $userIds[$au] = true;
            }
        }

        $projectsById = $projectRepository->findIndexedByIds(array_keys($projectIds));
        $usersById = $utilisateurRepository->findIndexedByIds(array_keys($userIds));

        $avatarUrlById = [];
        foreach ($usersById as $uid => $u) {
            $raw = $u->getImagelink();
            if (is_string($raw)) {
                $raw = trim($raw);
                if ($raw !== '' && preg_match('~^(https?://|/|data:image/)~', $raw) === 1) {
                    $avatarUrlById[(int) $uid] = $raw;
                }
            }
        }

        // Group tasks by project.
        $tasksByProjectId = [];
        foreach ($tasks as $t) {
            $pid = $t->getProjectId() ?? 0;
            $tasksByProjectId[$pid] ??= [];
            $tasksByProjectId[$pid][] = $t;
        }

        return $this->render('task/index.html.twig', [
            'q' => $q,
            'tasksByProjectId' => $tasksByProjectId,
            'projectsById' => $projectsById,
            'usersById' => $usersById,
            'avatarUrlById' => $avatarUrlById,
            'createForm' => $createForm->createView(),
        ]);
    }

    #[Route('/new', name: 'app_task_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $task = new Task();
        // Keep the legacy CRUD route around; UI uses the modal on /task.
        $form = $this->createForm(TaskType::class, $task);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($task);
            $entityManager->flush();

            return $this->redirectToRoute('app_task_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('task/new.html.twig', [
            'task' => $task,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_task_show', methods: ['GET'])]
    public function show(Task $task): Response
    {
        return $this->render('task/show.html.twig', [
            'task' => $task,
        ]);
    }

    #[Route('/{id}/status', name: 'app_task_status', methods: ['POST'])]
    public function status(Request $request, Task $task, EntityManagerInterface $entityManager): Response
    {
        $token = (string) $request->request->get('_token', '');
        if (!$this->isCsrfTokenValid('task_status'.$task->getId(), $token)) {
            throw $this->createAccessDeniedException();
        }

        $status = strtolower(trim((string) $request->request->get('status', '')));
        $allowed = ['todo', 'in_progress', 'done'];
        if (!in_array($status, $allowed, true)) {
            throw $this->createNotFoundException();
        }

        $task->setStatus($status);
        $task->setUpdatedAt(new \DateTime());
        $entityManager->flush();

        // Prefer returning the user to where they clicked from (internal paths only).
        $back = (string) $request->request->get('back', '');
        if ($back !== '' && str_starts_with($back, '/')) {
            return $this->redirect($back);
        }

        $projectId = $task->getProjectId();
        if ($projectId !== null) {
            return $this->redirectToRoute('app_project_show', ['id' => (int) $projectId, 'tab' => 'tasks']);
        }

        return $this->redirectToRoute('app_task_index');
    }

    #[Route('/{id}/edit', name: 'app_task_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Task $task, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(TaskType::class, $task);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            return $this->redirectToRoute('app_task_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('task/edit.html.twig', [
            'task' => $task,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_task_delete', methods: ['POST'])]
    public function delete(Request $request, Task $task, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$task->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($task);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_task_index', [], Response::HTTP_SEE_OTHER);
    }
}
