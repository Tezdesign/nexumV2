<?php

namespace App\Controller\tasks;

use App\Entity\Tasks\Task;

final class TaskWorkloadEngine
{
    private const WARNING_THRESHOLD = 8;
    private const BLOCK_THRESHOLD = 12;
    private const MAX_IN_PROGRESS = 3;
    private const MAX_OVERDUE = 2;

    /**
     * @param array<int, Task> $tasks
     *
     * @return array{
     *     score:int,
     *     decision:string,
     *     active_tasks:int,
     *     in_progress_count:int,
     *     overdue_count:int,
     *     reasons:array<int, string>
     * }
     */
    public static function assess(array $tasks, ?\DateTimeImmutable $now = null): array
    {
        $today = ($now ?? new \DateTimeImmutable('now'))->setTime(0, 0);
        $score = 0;
        $activeTasks = 0;
        $inProgressCount = 0;
        $overdueCount = 0;

        foreach ($tasks as $task) {
            $status = self::normalizeStatus($task->getStatus());
            if ($status === 'done') {
                continue;
            }

            $activeTasks++;
            if ($status === 'in_progress') {
                $inProgressCount++;
            }

            $score += self::priorityWeight($task->getPriority());
            $score += self::urgencyBonus($task->getDueDate(), $today);
            $score += $status === 'in_progress' ? 1 : 0;

            if (self::isOverdue($task->getDueDate(), $today)) {
                $overdueCount++;
            }
        }

        $decision = 'ok';
        if (
            $score >= self::BLOCK_THRESHOLD
            || $inProgressCount >= self::MAX_IN_PROGRESS
            || $overdueCount >= self::MAX_OVERDUE
        ) {
            $decision = 'blocked';
        } elseif ($score >= self::WARNING_THRESHOLD) {
            $decision = 'warning';
        }

        $reasons = [];
        if ($score >= self::BLOCK_THRESHOLD) {
            $reasons[] = sprintf('workload score is %d.', $score);
        } elseif ($score >= self::WARNING_THRESHOLD) {
            $reasons[] = sprintf('workload score is %d.', $score);
        }

        if ($inProgressCount >= self::MAX_IN_PROGRESS) {
            $reasons[] = sprintf('%d tasks are already in progress.', $inProgressCount);
        }

        if ($overdueCount >= self::MAX_OVERDUE) {
            $reasons[] = sprintf('%d active tasks are overdue.', $overdueCount);
        }

        return [
            'score' => $score,
            'decision' => $decision,
            'active_tasks' => $activeTasks,
            'in_progress_count' => $inProgressCount,
            'overdue_count' => $overdueCount,
            'reasons' => $reasons,
        ];
    }

    private static function normalizeStatus(?string $status): string
    {
        $normalized = strtolower(trim((string) $status));

        return match ($normalized) {
            'done', 'completed', 'complete', 'finished' => 'done',
            'in_progress', 'in progress', 'progress', 'doing', 'started' => 'in_progress',
            default => 'todo',
        };
    }

    private static function priorityWeight(?string $priority): int
    {
        return match (strtolower(trim((string) $priority))) {
            'high' => 3,
            'medium' => 2,
            default => 1,
        };
    }

    private static function urgencyBonus(?\DateTimeInterface $dueDate, \DateTimeImmutable $today): int
    {
        if ($dueDate === null) {
            return 0;
        }

        $dueDay = \DateTimeImmutable::createFromInterface($dueDate)->setTime(0, 0);
        if ($dueDay < $today) {
            return 2;
        }

        $daysUntilDue = (int) $today->diff($dueDay)->format('%a');
        if ($daysUntilDue <= 2) {
            return 1;
        }

        return 0;
    }

    private static function isOverdue(?\DateTimeInterface $dueDate, \DateTimeImmutable $today): bool
    {
        if ($dueDate === null) {
            return false;
        }

        return \DateTimeImmutable::createFromInterface($dueDate)->setTime(0, 0) < $today;
    }
}
