<?php

namespace App\Controller\Project;

use App\Entity\Tasks\Task;

final class ProjectProgressEngine
{
    /**
     * @param array<int, Task> $tasks
     *
     * @return array<string, mixed>
     */
    public static function build(array $tasks, ?\DateTimeImmutable $now = null): array
    {
        $today = ($now ?? new \DateTimeImmutable('now'))->setTime(0, 0);
        $weekStart = $today->modify('monday this week')->setTime(0, 0);
        $weekEnd = $weekStart->modify('+6 days')->setTime(23, 59, 59);

        $weightedCompletion = 0;
        $completedCount = 0;
        $overdueCount = 0;
        $memberStats = [];

        foreach ($tasks as $task) {
            $status = self::normalizeStatus($task->getStatus());
            $weightedCompletion += match ($status) {
                'done' => 100,
                'in_progress' => 50,
                default => 0,
            };
            if ($status === 'done') {
                $completedCount++;
            }

            $dueDate = self::toDay($task->getDueDate());
            $completedAt = $task->getUpdatedAt();
            $completedDay = $completedAt ? \DateTimeImmutable::createFromInterface($completedAt)->setTime(0, 0) : null;
            $isDone = $status === 'done';
            $isOverdue = !$isDone && $dueDate !== null && $dueDate < $today;

            if ($isOverdue) {
                $overdueCount++;
            }

            $assignedTo = $task->getAssignedTo();
            if ($assignedTo !== null && $assignedTo > 0) {
                if (!isset($memberStats[$assignedTo])) {
                    $memberStats[$assignedTo] = [
                        'member_id' => $assignedTo,
                        'points' => 0,
                        'max_points' => 0,
                        'completed_this_week' => 0,
                        'completed_count' => 0,
                        'completed_on_time_count' => 0,
                    ];
                }

                $memberStats[$assignedTo]['max_points'] += 10;

                if ($isDone) {
                    $isOnTime = $dueDate === null || ($completedDay !== null && $completedDay <= $dueDate);
                    $memberStats[$assignedTo]['points'] += $isOnTime ? 10 : 5;
                    $memberStats[$assignedTo]['completed_count']++;

                    if ($isOnTime) {
                        $memberStats[$assignedTo]['completed_on_time_count']++;
                    }

                    if ($completedAt !== null && $completedAt >= $weekStart && $completedAt <= $weekEnd) {
                        $memberStats[$assignedTo]['completed_this_week']++;
                    }
                } elseif ($status === 'todo' && $isOverdue) {
                    $memberStats[$assignedTo]['points'] -= 5;
                } elseif ($status === 'in_progress' && ($dueDate === null || $dueDate >= $today)) {
                    $memberStats[$assignedTo]['points'] += 3;
                }
            }
        }

        foreach ($memberStats as &$memberStat) {
            $maxPoints = (int) $memberStat['max_points'];
            $memberCompletedCount = (int) $memberStat['completed_count'];

            $memberStat['productivity_score'] = max(0, min(100, (int) round(($memberStat['points'] / $maxPoints) * 100)));
            $memberStat['on_time_delivery_rate'] = $memberCompletedCount > 0
                ? (int) round(($memberStat['completed_on_time_count'] / $memberCompletedCount) * 100)
                : null;
        }
        unset($memberStat);

        usort($memberStats, static function (array $left, array $right): int {
            if ($left['productivity_score'] !== $right['productivity_score']) {
                return $right['productivity_score'] <=> $left['productivity_score'];
            }

            return $right['completed_count'] <=> $left['completed_count'];
        });

        $taskCount = count($tasks);
        if ($taskCount === 0) {
            return [
                'completion_percentage' => 0,
                'tasks_total' => 0,
                'tasks_completed' => 0,
                'tasks_overdue' => 0,
                'member_productivity' => [],
            ];
        }

        return [
            'completion_percentage' => (int) round($weightedCompletion / $taskCount),
            'tasks_total' => $taskCount,
            'tasks_completed' => $completedCount,
            'tasks_overdue' => $overdueCount,
            'member_productivity' => $memberStats,
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

    private static function toDay(?\DateTimeInterface $date): ?\DateTimeImmutable
    {
        return $date ? \DateTimeImmutable::createFromInterface($date)->setTime(0, 0) : null;
    }
}
