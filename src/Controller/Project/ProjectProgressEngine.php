<?php

namespace App\Controller\Project;

use App\Entity\Projects\Project;
use App\Entity\Tasks\Task;

final class ProjectProgressEngine
{
    /**
     * @param array<int, Task> $tasks
     *
     * @return array<string, mixed>
     */
    public static function build(Project $project, array $tasks, ?\DateTimeImmutable $now = null): array
    {
        $today = ($now ?? new \DateTimeImmutable('now'))->setTime(0, 0);
        $weekStart = $today->modify('monday this week')->setTime(0, 0);
        $weekEnd = $weekStart->modify('+6 days')->setTime(23, 59, 59);

        $statusCounts = [
            'todo' => 0,
            'in_progress' => 0,
            'done' => 0,
        ];
        $weightedCompletion = 0;
        $overdueCount = 0;
        $onTrackCount = 0;
        $estimatedTotal = 0;
        $actualTotal = 0;
        $hasEstimatedTime = false;
        $hasActualTime = false;
        $memberStats = [];
        $taskInsights = [];

        foreach ($tasks as $task) {
            if (!$task instanceof Task) {
                continue;
            }

            $status = self::normalizeStatus($task->getStatus());
            $statusCounts[$status]++;
            $weightedCompletion += match ($status) {
                'done' => 100,
                'in_progress' => 50,
                default => 0,
            };

            $dueDate = self::toDay($task->getDueDate());
            $completedAt = $task->getUpdatedAt();
            $completedDay = $completedAt ? \DateTimeImmutable::createFromInterface($completedAt)->setTime(0, 0) : null;
            $isDone = $status === 'done';
            $isOverdue = !$isDone && $dueDate !== null && $dueDate < $today;

            if ($isOverdue) {
                $overdueCount++;
            } else {
                $onTrackCount++;
            }

            $estimated = $task->getEstimatedTime();
            if ($estimated !== null) {
                $hasEstimatedTime = true;
                $estimatedTotal += $estimated;
            }

            $actual = $task->getActualTime();
            if ($actual !== null) {
                $hasActualTime = true;
                $actualTotal += $actual;
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
                        'completion_time_total' => 0,
                        'completion_time_count' => 0,
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

                    if ($actual !== null) {
                        $memberStats[$assignedTo]['completion_time_total'] += $actual;
                        $memberStats[$assignedTo]['completion_time_count']++;
                    }
                } elseif ($status === 'todo' && $isOverdue) {
                    $memberStats[$assignedTo]['points'] -= 5;
                } elseif ($status === 'in_progress' && ($dueDate === null || $dueDate >= $today)) {
                    $memberStats[$assignedTo]['points'] += 3;
                }
            }

            $taskInsights[] = [
                'id' => $task->getId(),
                'title' => $task->getTitle(),
                'status' => $status,
                'assigned_to' => $assignedTo,
                'due_date' => $task->getDueDate(),
                'days_remaining' => (!$isDone && $dueDate !== null && $dueDate >= $today)
                    ? (int) $today->diff($dueDate)->format('%a')
                    : null,
                'overdue_by_days' => $isOverdue
                    ? (int) $dueDate->diff($today)->format('%a')
                    : null,
                'estimated_time' => $estimated,
                'actual_time' => $actual,
                'time_variance' => ($estimated !== null && $actual !== null) ? ($actual - $estimated) : null,
            ];
        }

        usort($taskInsights, static function (array $left, array $right): int {
            $leftOverdue = $left['overdue_by_days'] ?? -1;
            $rightOverdue = $right['overdue_by_days'] ?? -1;
            if ($leftOverdue !== $rightOverdue) {
                return $rightOverdue <=> $leftOverdue;
            }

            $leftRemaining = $left['days_remaining'] ?? PHP_INT_MAX;
            $rightRemaining = $right['days_remaining'] ?? PHP_INT_MAX;
            return $leftRemaining <=> $rightRemaining;
        });

        foreach ($memberStats as &$memberStat) {
            $maxPoints = (int) $memberStat['max_points'];
            $completedCount = (int) $memberStat['completed_count'];
            $completionTimeCount = (int) $memberStat['completion_time_count'];

            $memberStat['productivity_score'] = $maxPoints > 0
                ? max(0, min(100, (int) round(($memberStat['points'] / $maxPoints) * 100)))
                : 0;
            $memberStat['average_completion_time'] = $completionTimeCount > 0
                ? (int) round($memberStat['completion_time_total'] / $completionTimeCount)
                : null;
            $memberStat['on_time_delivery_rate'] = $completedCount > 0
                ? (int) round(($memberStat['completed_on_time_count'] / $completedCount) * 100)
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
        $projectBudget = self::toFloat($project->getBudget());
        $budgetUtilization = ($projectBudget !== null && $projectBudget > 0 && $actualTotal > 0)
            ? round(($actualTotal / $projectBudget) * 100, 1)
            : null;

        return [
            'completion_percentage' => $taskCount > 0 ? (int) round($weightedCompletion / $taskCount) : 0,
            'status_counts' => $statusCounts,
            'tasks_total' => $taskCount,
            'tasks_on_track' => $onTrackCount,
            'tasks_overdue' => $overdueCount,
            'estimated_time_total' => $hasEstimatedTime ? $estimatedTotal : null,
            'actual_time_total' => $hasActualTime ? $actualTotal : null,
            'time_utilization_percentage' => ($estimatedTotal > 0 && $actualTotal > 0)
                ? (int) round(($actualTotal / $estimatedTotal) * 100)
                : null,
            'budget_value' => $projectBudget,
            'budget_utilization_percentage' => $budgetUtilization,
            'member_productivity' => $memberStats,
            'task_insights' => $taskInsights,
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

    private static function toFloat(string|int|float|null $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        return is_numeric($value) ? (float) $value : null;
    }
}
