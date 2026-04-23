<?php

namespace App\Controller\Project;

use App\Entity\Projects\Project;
use App\Entity\Projects\ProjectFile;
use App\Repository\Projects\ProjectAssignmentRepository;
use App\Repository\Projects\ProjectRepository;
use App\Service\AuthService;
use App\Service\Project\ProjectFileStorage;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Profiler\Profiler;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/project')]
final class ProjectFileController extends AbstractController
{
    #[Route('/{id}/files/upload', name: 'app_project_file_upload', methods: ['POST'])]
    public function upload(
        Request $request,
        Project $project,
        AuthService $authService,
        ProjectRepository $projectRepository,
        ProjectAssignmentRepository $projectAssignmentRepository,
        ProjectFileStorage $projectFileStorage,
        EntityManagerInterface $entityManager,
        ?Profiler $profiler = null,
    ): Response {
        $profiler?->disable();

        $currentUserId = (int) ($authService->getCurrentUserId() ?? 0);
        $isManager = $authService->isManager();

        $visibleProjectIds = $isManager ? [] : array_fill_keys(array_values(array_unique(array_merge(
            $projectRepository->getProjectIdsForUser($currentUserId),
            $projectAssignmentRepository->getProjectIdsByUserId($currentUserId),
        ))), true);

        $canUpload = $currentUserId > 0
            && ($isManager || isset($visibleProjectIds[(int) ($project->getId() ?? 0)]));

        if (!$canUpload) {
            throw $this->createAccessDeniedException();
        }

        if (!$this->isCsrfTokenValid('project_file_upload_' . (int) $project->getId(), (string) $request->request->get('_token'))) {
            return $this->redirectToRoute('app_project_show', [
                'id' => $project->getId(),
                'tab' => 'files',
            ], Response::HTTP_SEE_OTHER);
        }

        $contentLength = (int) ($request->server->get('CONTENT_LENGTH') ?? 0);
        $postMaxSize = $this->parseIniSize((string) ini_get('post_max_size'));
        if ($contentLength > 0 && $postMaxSize > 0 && $contentLength > $postMaxSize) {
            $this->addFlash('danger', sprintf(
                'The selected file is larger than the server upload limit (%s).',
                (string) ini_get('post_max_size')
            ));

            return $this->redirectToRoute('app_project_show', [
                'id' => $project->getId(),
                'tab' => 'files',
            ], Response::HTTP_SEE_OTHER);
        }

        $uploaded = $request->files->get('project_file');
        if (!$uploaded instanceof UploadedFile) {
            $this->addFlash('warning', 'Choose a file before uploading.');

            return $this->redirectToRoute('app_project_show', [
                'id' => $project->getId(),
                'tab' => 'files',
            ], Response::HTTP_SEE_OTHER);
        }

        if (!$uploaded->isValid()) {
            $message = match ($uploaded->getError()) {
                \UPLOAD_ERR_INI_SIZE, \UPLOAD_ERR_FORM_SIZE => sprintf(
                    'The selected file is larger than the server upload limit (%s).',
                    (string) ini_get('upload_max_filesize')
                ),
                default => 'The selected file could not be uploaded.',
            };

            $this->addFlash('danger', $message);

            return $this->redirectToRoute('app_project_show', [
                'id' => $project->getId(),
                'tab' => 'files',
            ], Response::HTTP_SEE_OTHER);
        }

        try {
            $stored = $projectFileStorage->upload($uploaded, (int) $project->getId());
        } catch (\Throwable $e) {
            $this->addFlash('danger', $e->getMessage());

            return $this->redirectToRoute('app_project_show', [
                'id' => $project->getId(),
                'tab' => 'files',
            ], Response::HTTP_SEE_OTHER);
        }

        $record = (new ProjectFile())
            ->setProject_id((int) $project->getId())
            ->setUploaded_by($currentUserId)
            ->setOriginal_name($uploaded->getClientOriginalName() ?: $uploaded->getFilename())
            ->setPublic_id($stored['public_id'])
            ->setResource_type($stored['resource_type'])
            ->setFormat($stored['format'])
            ->setBytes($stored['bytes'])
            ->setSecure_url($stored['secure_url']);

        $entityManager->persist($record);
        $entityManager->flush();

        $this->addFlash('success', 'File uploaded successfully.');

        return $this->redirectToRoute('app_project_show', [
            'id' => $project->getId(),
            'tab' => 'files',
        ], Response::HTTP_SEE_OTHER);
    }

    private function parseIniSize(string $value): int
    {
        $trimmed = trim($value);
        if ($trimmed === '') {
            return 0;
        }

        $unit = strtolower(substr($trimmed, -1));
        $number = (float) $trimmed;

        return match ($unit) {
            'g' => (int) ($number * 1024 * 1024 * 1024),
            'm' => (int) ($number * 1024 * 1024),
            'k' => (int) ($number * 1024),
            default => (int) $number,
        };
    }
}
