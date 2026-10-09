<?php

namespace App\Service\ResourcesManagement;

use App\Repository\ResourcesManagement\ResourceAssignmentRepository;
use App\Repository\ResourcesManagement\ResourceRepository;
use Psr\Log\LoggerInterface;
use Symfony\Component\Process\Process;

/** Demand forecast: builds the dataset from assignments and runs python/forecast.py on it. */
final class ResourceForecastService
{
    public function __construct(
        private readonly ResourceAssignmentRepository $assignments,
        private readonly ResourceRepository $resources,
        private readonly LoggerInterface $logger,
        private readonly string $projectDir,
        private readonly string $pythonBin = 'python',
    ) {
    }

    /**
     * One row per assignment that has a start date and a known resource.
     *
     * @return array<int, array{resource_id: ?int, resource_name: ?string, type: ?string, quantity: ?int, date: string}>
     */
    public function dataset(): array
    {
        $resourceById = [];
        foreach ($this->resources->findAll() as $resource) {
            $resourceById[$resource->getResourceId()] = $resource;
        }

        $data = [];
        foreach ($this->assignments->findAll() as $assignment) {
            $date = $assignment->getAssignmentDate();
            $resource = $resourceById[$assignment->getResourceId()] ?? null;
            if ($date === null || $resource === null) {
                continue;
            }

            $data[] = [
                'resource_id' => $resource->getResourceId(),
                'resource_name' => $resource->getResourceName(),
                'type' => $resource->getResourceType(),
                'quantity' => $assignment->getQuantity(),
                'date' => $date->format('Y-m-d'),
            ];
        }

        return $data;
    }

    /**
     * Predicted demand for the next period, 0.0 when there is no history, or null when the
     * script could not run (the reason is logged).
     *
     * @param array<int, array<string, mixed>> $data
     */
    public function forecast(array $data): ?float
    {
        if ($data === []) {
            return 0.0;
        }

        $process = new Process([$this->pythonBin, $this->projectDir . '/python/forecast.py'], null, null, json_encode($data), 20);

        try {
            $process->run();
        } catch (\Throwable $e) {
            $this->logger->error('Resource forecast could not start', ['python' => $this->pythonBin, 'exception' => $e]);

            return null;
        }

        $output = trim($process->getOutput());
        if (!$process->isSuccessful() || trim($process->getErrorOutput()) !== '' || !is_numeric($output)) {
            // forecast.py prints "ERROR: ..." on stdout when it fails, so log both streams.
            $this->logger->error('Resource forecast failed', [
                'python' => $this->pythonBin,
                'exit_code' => $process->getExitCode(),
                'stdout' => $output,
                'stderr' => trim($process->getErrorOutput()),
            ]);

            return null;
        }

        return round((float) $output, 2);
    }
}
