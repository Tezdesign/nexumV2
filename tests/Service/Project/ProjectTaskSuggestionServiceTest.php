<?php

namespace App\Tests\Service\Project;

use App\Service\Project\AI\GeminiClient;
use App\Service\Project\AI\ProjectTaskSuggestionService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;

final class ProjectTaskSuggestionServiceTest extends TestCase
{
    private function service(): ProjectTaskSuggestionService
    {
        return new ProjectTaskSuggestionService(new GeminiClient(new MockHttpClient(), 'key', 'model'));
    }

    /** @dataProvider nothingAccepted */
    public function testNothingAcceptedCreatesNoTasks(string $json): void
    {
        $this->assertSame([], $this->service()->normalizeAcceptedSuggestions(
            $json,
            'Demo',
            new \DateTimeImmutable('2026-01-01'),
            new \DateTimeImmutable('2026-01-31'),
        ));
    }

    /** @return iterable<string, array{string}> */
    public static function nothingAccepted(): iterable
    {
        yield 'default empty list' => ['[]'];
        yield 'invalid json' => ['not json'];
    }

    public function testOnlyAcceptedTasksAreKeptAndDatesAreClamped(): void
    {
        $tasks = $this->service()->normalizeAcceptedSuggestions(
            '[{"title":"Write spec","priority":"high","due_offset_days":99}]',
            'Demo',
            new \DateTimeImmutable('2026-01-01'),
            new \DateTimeImmutable('2026-01-31'),
        );

        $this->assertCount(1, $tasks);
        $this->assertSame('Write spec', $tasks[0]['title']);
        $this->assertSame('2026-01-31', $tasks[0]['due_date']);
    }

    public function testSuggestionsPostedBackKeepTheirDueDate(): void
    {
        $start = new \DateTimeImmutable('2026-01-01');
        $end = new \DateTimeImmutable('2026-01-31');
        $json = '[{"title":"Design","priority":"low","due_date":"2026-01-10"},'
            . '{"title":"Release","priority":"high","due_date":"2026-02-20"},'
            . '{"title":"Bad date","priority":"low","due_date":"soon"}]';

        $tasks = $this->service()->normalizeAcceptedSuggestions($json, 'Demo', $start, $end);

        $this->assertSame(['2026-01-10', '2026-01-31', '2026-01-01'], array_column($tasks, 'due_date'));
    }
}
