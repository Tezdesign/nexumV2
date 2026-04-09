<?php

namespace App\Service;

use Symfony\Component\HttpKernel\KernelInterface;

final class ProjectActivityLogger
{
    public function __construct(private readonly KernelInterface $kernel)
    {
    }

    public function record(int $projectId, string $actorName, string $action, string $message): void
    {
        $entry = [
            'projectId' => $projectId,
            'actorName' => $actorName,
            'action' => $action,
            'message' => $message,
            'createdAt' => (new \DateTimeImmutable())->format(DATE_ATOM),
        ];

        $path = $this->getPath();
        $dir = dirname($path);
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        @file_put_contents($path, json_encode($entry, JSON_UNESCAPED_UNICODE) . PHP_EOL, FILE_APPEND | LOCK_EX);
    }

    private function getPath(): string
    {
        return rtrim($this->kernel->getProjectDir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'var' . DIRECTORY_SEPARATOR . 'project-activity.jsonl';
    }
}
