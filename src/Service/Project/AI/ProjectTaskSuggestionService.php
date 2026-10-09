<?php

namespace App\Service\Project\AI;

use App\Support\PlainTextSanitizer;

final class ProjectTaskSuggestionService
{
    public function __construct(
        private readonly GeminiClient $geminiClient,
    ) {
    }

    /**
     * @return array<int, array{
     *     title: string,
     *     description: ?string,
     *     priority: string,
     *     status: string,
     *     due_date: string
     * }>
     */
    public function suggest(
        string $name,
        ?string $description,
        \DateTimeInterface $startDate,
        \DateTimeInterface $endDate,
    ): array {
        $rawTasks = $this->geminiClient->suggestProjectTasks($name, $description, $startDate, $endDate);

        return $this->normalizeTasks($rawTasks, $name, $startDate, $endDate, padWithFallback: true);
    }

    /**
     * @return array<int, array{
     *     title: string,
     *     description: ?string,
     *     priority: string,
     *     status: string,
     *     due_date: string
     * }>
     */
    public function normalizeAcceptedSuggestions(
        string $json,
        string $projectName,
        \DateTimeInterface $startDate,
        \DateTimeInterface $endDate,
    ): array {
        $decoded = json_decode($json, true);
        if (!is_array($decoded)) {
            return [];
        }

        $rows = array_values(array_filter($decoded, static fn (mixed $row): bool => is_array($row)));

        // Only what the user accepted: an empty list must stay empty.
        return $this->normalizeTasks($rows, $projectName, $startDate, $endDate, padWithFallback: false);
    }

    /**
     * @param array<int, array<string, mixed>> $rawTasks
     * @return array<int, array{
     *     title: string,
     *     description: ?string,
     *     priority: string,
     *     status: string,
     *     due_date: string
     * }>
     */
    private function normalizeTasks(
        array $rawTasks,
        string $projectName,
        \DateTimeInterface $startDate,
        \DateTimeInterface $endDate,
        bool $padWithFallback,
    ): array {
        $normalized = [];
        $start = \DateTimeImmutable::createFromInterface($startDate)->setTime(0, 0);
        $end = \DateTimeImmutable::createFromInterface($endDate)->setTime(0, 0);
        if ($end < $start) {
            $end = $start;
        }

        $durationDays = (int) $start->diff($end)->days;

        foreach ($rawTasks as $row) {
            if (count($normalized) >= 5) {
                break;
            }

            $title = PlainTextSanitizer::toLine((string) ($row['title'] ?? ''));
            if ($title === '') {
                continue;
            }

            $description = PlainTextSanitizer::toBlock(isset($row['description']) ? (string) $row['description'] : null);
            $priority = strtolower(trim((string) ($row['priority'] ?? 'medium')));
            if (!in_array($priority, ['high', 'medium', 'low'], true)) {
                $priority = 'medium';
            }

            $offset = $this->dueOffsetDays($row, $start);
            $offset = max(0, min($durationDays, $offset));
            $dueDate = $start->modify('+' . $offset . ' days');

            $normalized[] = [
                'title' => $title,
                'description' => $description,
                'priority' => $priority,
                'status' => 'todo',
                'due_date' => $dueDate->format('Y-m-d'),
            ];
        }

        if ($padWithFallback && count($normalized) < 5) {
            foreach ($this->fallbackTasks($projectName, $start, $end) as $fallback) {
                if (count($normalized) >= 5) {
                    break;
                }

                $exists = array_filter($normalized, static fn (array $task): bool => $task['title'] === $fallback['title']);
                if ($exists !== []) {
                    continue;
                }

                $normalized[] = $fallback;
            }
        }

        return array_slice($normalized, 0, 5);
    }

    /**
     * Gemini answers with `due_offset_days`; suggestions already cleaned by `suggest()` come back from the
     * browser with `due_date` instead, so accept either.
     *
     * @param array<string, mixed> $row
     */
    private function dueOffsetDays(array $row, \DateTimeImmutable $start): int
    {
        if (isset($row['due_offset_days'])) {
            return (int) $row['due_offset_days'];
        }

        $due = \DateTimeImmutable::createFromFormat('!Y-m-d', (string) ($row['due_date'] ?? ''));

        return $due instanceof \DateTimeImmutable ? (int) $start->diff($due)->format('%r%a') : 0;
    }

    /**
     * @return array<int, array{
     *     title: string,
     *     description: ?string,
     *     priority: string,
     *     status: string,
     *     due_date: string
     * }>
     */
    private function fallbackTasks(
        string $projectName,
        \DateTimeImmutable $startDate,
        \DateTimeImmutable $endDate,
    ): array {
        $durationDays = max(0, (int) $startDate->diff($endDate)->days);
        $milestones = [
            ['Project kickoff and scope alignment', 'Confirm the project scope, roles, success criteria, and working plan.', 'high', 0],
            ['Requirements and deliverables breakdown', 'Translate the project goals into a clear list of deliverables and priorities.', 'high', max(0, (int) floor($durationDays * 0.2))],
            ['Execution checkpoint for ' . PlainTextSanitizer::toLine($projectName), 'Review progress, unblock issues, and adjust the delivery plan if needed.', 'medium', max(0, (int) floor($durationDays * 0.5))],
            ['Quality review and stakeholder feedback', 'Validate the work completed so far and collect any required revisions.', 'medium', max(0, (int) floor($durationDays * 0.75))],
            ['Final delivery and handoff', 'Prepare the final delivery package, handoff notes, and close-out items.', 'high', $durationDays],
        ];

        $tasks = [];
        foreach ($milestones as [$title, $description, $priority, $offset]) {
            $dueDate = $startDate->modify('+' . (int) $offset . ' days');
            if ($dueDate > $endDate) {
                $dueDate = $endDate;
            }

            $tasks[] = [
                'title' => $title,
                'description' => $description,
                'priority' => $priority,
                'status' => 'todo',
                'due_date' => $dueDate->format('Y-m-d'),
            ];
        }

        return $tasks;
    }
}
