<?php

namespace App\Service\Project\AI;

use Symfony\Contracts\HttpClient\HttpClientInterface;

final class GeminiClient
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string $apiKey,
        private readonly string $model,
    ) {
    }

    /**
     * @return array<int, array{
     *     title: string,
     *     description: string,
     *     priority: string,
     *     due_offset_days: int
     * }>
     */
    public function suggestProjectTasks(
        string $name,
        ?string $description,
        \DateTimeInterface $startDate,
        \DateTimeInterface $endDate,
    ): array {
        $response = $this->httpClient->request('POST', sprintf(
            'https://generativelanguage.googleapis.com/v1beta/models/%s:generateContent?key=%s',
            rawurlencode($this->model),
            rawurlencode($this->apiKey)
        ), [
            'headers' => [
                'Content-Type' => 'application/json',
            ],
            'json' => [
                'systemInstruction' => [
                    'parts' => [
                        [
                            'text' => implode("\n", [
                                'You are a project planning assistant.',
                                'Return exactly 5 practical task suggestions for a newly created project.',
                                'Focus on realistic execution steps, not vague goals.',
                                'Keep titles concise and descriptions short.',
                                'Use only priorities high, medium, or low.',
                                'Use due_offset_days as a non-negative integer counted from the project start date.',
                                'Do not include any text outside the JSON response.',
                            ]),
                        ],
                    ],
                ],
                'contents' => [
                    [
                        'parts' => [
                            [
                                'text' => implode("\n", [
                                    'Project name: ' . $name,
                                    'Project description: ' . ($description !== null && trim($description) !== '' ? trim($description) : 'No description provided.'),
                                    'Project start date: ' . $startDate->format('Y-m-d'),
                                    'Project end date: ' . $endDate->format('Y-m-d'),
                                    'Suggest 5 starter tasks for this project.',
                                ]),
                            ],
                        ],
                    ],
                ],
                'generationConfig' => [
                    'temperature' => 0.2,
                    'responseMimeType' => 'application/json',
                    'responseJsonSchema' => [
                        'type' => 'object',
                        'additionalProperties' => false,
                        'properties' => [
                            'tasks' => [
                                'type' => 'array',
                                'minItems' => 5,
                                'maxItems' => 5,
                                'items' => [
                                    'type' => 'object',
                                    'additionalProperties' => false,
                                    'properties' => [
                                        'title' => [
                                            'type' => 'string',
                                            'description' => 'Short task title.',
                                        ],
                                        'description' => [
                                            'type' => 'string',
                                            'description' => 'Short explanation of the task.',
                                        ],
                                        'priority' => [
                                            'type' => 'string',
                                            'enum' => ['high', 'medium', 'low'],
                                        ],
                                        'due_offset_days' => [
                                            'type' => 'integer',
                                            'minimum' => 0,
                                            'description' => 'Non-negative number of days after project start.',
                                        ],
                                    ],
                                    'required' => ['title', 'description', 'priority', 'due_offset_days'],
                                ],
                            ],
                        ],
                        'required' => ['tasks'],
                    ],
                ],
            ],
        ]);

        $data = $response->toArray(false);
        $statusCode = $response->getStatusCode();

        if ($statusCode < 200 || $statusCode >= 300) {
            $message = is_array($data['error'] ?? null)
                ? (string) ($data['error']['message'] ?? 'Gemini task suggestion request failed.')
                : 'Gemini task suggestion request failed.';

            throw new \RuntimeException($message);
        }

        $content = $data['candidates'][0]['content']['parts'][0]['text'] ?? null;
        if (!is_string($content) || trim($content) === '') {
            throw new \RuntimeException('Gemini returned an empty task suggestion response.');
        }

        $decoded = json_decode($content, true);
        if (!is_array($decoded) || !is_array($decoded['tasks'] ?? null)) {
            throw new \RuntimeException('Gemini returned an invalid task suggestion format.');
        }

        return array_values(array_filter($decoded['tasks'], static fn (mixed $task): bool => is_array($task)));
    }
}
