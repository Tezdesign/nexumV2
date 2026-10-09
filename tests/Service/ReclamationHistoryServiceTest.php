<?php

namespace App\Tests\Service;

use App\Service\AuthService;
use App\Service\ReclamationHistoryService;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

final class ReclamationHistoryServiceTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/rec_hist_' . uniqid();
    }

    protected function tearDown(): void
    {
        foreach (glob($this->dir . '/var/reclamation_history/*') ?: [] as $file) {
            unlink($file);
        }
    }

    private function service(?array $actor = ['id' => 7, 'email' => 'admin@nexum.test']): ReclamationHistoryService
    {
        $auth = $this->createMock(AuthService::class);
        $auth->method('getCurrentUser')->willReturn($actor);
        $auth->method('getCurrentUserId')->willReturn($actor['id'] ?? null);
        $stack = new RequestStack();
        $stack->push(Request::create('/', 'GET', [], [], [], ['REMOTE_ADDR' => '203.0.113.9']));

        return new ReclamationHistoryService(new NullLogger(), $auth, $stack, $this->dir);
    }

    public function testEntryRecordsWhoDidIt(): void
    {
        $service = $this->service();
        $service->logActivity(5, 'create', ['titre' => 'x']);

        $entry = $service->getReclamationHistory(5)[0];
        $this->assertSame(7, $entry['user_id']);
        $this->assertSame('admin@nexum.test', $entry['user_email']);
        $this->assertSame('203.0.113.9', $entry['ip']);
    }

    public function testHistoriesAreSeparatedAndNewestFirst(): void
    {
        $service = $this->service();
        $service->logActivity(5, 'create');
        $service->logActivity(6, 'create');
        $service->logActivity(5, 'delete');

        $this->assertSame(['delete', 'create'], array_column($service->getReclamationHistory(5), 'action'));
        $this->assertCount(1, $service->getReclamationHistory(6));
        $this->assertSame([], $service->getReclamationHistory(99));
    }

    public function testNoLoginIsRecordedAsSystem(): void
    {
        $service = $this->service(null);
        $service->logActivity(5, 'create');

        $this->assertSame('system', $service->getReclamationHistory(5)[0]['user_email']);
    }
}
