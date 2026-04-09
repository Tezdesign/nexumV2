<?php

namespace App\Service;

use Symfony\Component\HttpKernel\KernelInterface;

final class ProjectActivityFeed
{
    public function __construct(private readonly KernelInterface $kernel)
    {
    }

    /**
     * @return array<int, array{projectId:int, actorName:string, action:string, message:string, createdAt:\DateTimeImmutable}>
     */
    public function findRecentForProject(int $projectId, int $limit = 6): array
    {
        if ($projectId <= 0 || $limit <= 0) {
            return [];
        }

        $path = $this->getPath();
        if (!is_file($path)) {
            return [];
        }

        $lines = @file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if (!is_array($lines) || $lines === []) {
            return [];
        }

        $items = [];
        for ($i = count($lines) - 1; $i >= 0; $i--) {
            $row = json_decode((string) $lines[$i], true);
            if (!is_array($row)) {
                continue;
            }

            $rowProjectId = (int) ($row['projectId'] ?? 0);
            if ($rowProjectId !== $projectId) {
                continue;
            }

            $createdAtRaw = (string) ($row['createdAt'] ?? '');
            try {
                $createdAt = new \DateTimeImmutable($createdAtRaw);
            } catch (\Throwable) {
                $createdAt = new \DateTimeImmutable('@0');
            }

            $items[] = [
                'projectId' => $rowProjectId,
                'actorName' => (string) ($row['actorName'] ?? 'Unknown user'),
                'action' => (string) ($row['action'] ?? 'updated'),
                'message' => (string) ($row['message'] ?? ''),
                'createdAt' => $createdAt,
            ];

            if (count($items) >= $limit) {
                break;
            }
        }

        return $items;
    }

    private function getPath(): string
    {
        return rtrim($this->kernel->getProjectDir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'var' . DIRECTORY_SEPARATOR . 'project-activity.jsonl';
    }
}
