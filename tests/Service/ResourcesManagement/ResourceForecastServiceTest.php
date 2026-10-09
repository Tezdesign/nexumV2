<?php

namespace App\Tests\Service\ResourcesManagement;

use App\Entity\ResourcesManagement\Resource;
use App\Entity\ResourcesManagement\ResourceAssignment;
use App\Repository\ResourcesManagement\ResourceAssignmentRepository;
use App\Repository\ResourcesManagement\ResourceRepository;
use App\Service\ResourcesManagement\ResourceForecastService;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

final class ResourceForecastServiceTest extends TestCase
{
    /** @param list<ResourceAssignment> $assignments @param list<Resource> $resources */
    private function service(string $python = 'python', array $assignments = [], array $resources = [], ?LoggerInterface $logger = null): ResourceForecastService
    {
        $assignmentRepo = $this->createMock(ResourceAssignmentRepository::class);
        $assignmentRepo->method('findAll')->willReturn($assignments);
        $resourceRepo = $this->createMock(ResourceRepository::class);
        $resourceRepo->method('findAll')->willReturn($resources);

        return new ResourceForecastService($assignmentRepo, $resourceRepo, $logger ?? $this->createMock(LoggerInterface::class), dirname(__DIR__, 3), $python);
    }

    public function testNoHistoryMeansZeroWithoutRunningPython(): void
    {
        $this->assertSame(0.0, $this->service('definitely-not-a-python')->forecast([]));
    }

    public function testMissingInterpreterIsReportedAsFailureAndLogged(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('error');

        $result = $this->service('definitely-not-a-python', logger: $logger)->forecast([['date' => '2026-01-01', 'quantity' => 1]]);

        $this->assertNull($result);
    }

    public function testRealScriptPredictsTheNextPointOfALine(): void
    {
        $python = trim((string) shell_exec('command -v python3 || command -v python'));
        if ($python === '' || trim((string) shell_exec(escapeshellarg($python).' -c "import sklearn, numpy; print(1)" 2>/dev/null')) !== '1') {
            $this->markTestSkipped('Python with scikit-learn is not available.');
        }

        $data = [
            ['date' => '2026-01-01', 'quantity' => 2],
            ['date' => '2026-01-02', 'quantity' => 4],
            ['date' => '2026-01-03', 'quantity' => 6],
        ];

        $this->assertSame(8.0, $this->service($python)->forecast($data));
    }

    public function testDatasetSkipsAssignmentsWithoutDateOrKnownResource(): void
    {
        $resource = $this->createMock(Resource::class);
        $resource->method('getResourceId')->willReturn(7);
        $resource->method('getResourceName')->willReturn('Laptop');
        $resource->method('getResourceType')->willReturn('PHYSICAL');

        $good = (new ResourceAssignment())->setResourceId(7)->setQuantity(3)->setAssignmentDate(new \DateTime('2026-02-05'));
        $unknownResource = (new ResourceAssignment())->setResourceId(99)->setQuantity(1)->setAssignmentDate(new \DateTime('2026-02-06'));

        $data = $this->service(assignments: [$good, $unknownResource], resources: [$resource])->dataset();

        $this->assertSame([[
            'resource_id' => 7,
            'resource_name' => 'Laptop',
            'type' => 'PHYSICAL',
            'quantity' => 3,
            'date' => '2026-02-05',
        ]], $data);
    }
}
