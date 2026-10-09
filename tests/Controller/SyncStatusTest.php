<?php

namespace App\Tests\Controller;

use App\Entity\FinancialAnalysis\Transaction;
use App\EventSubscriber\SyncLogSubscriber;
use App\Kernel;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\Event\PostPersistEventArgs;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Contracts\Cache\CacheInterface;

final class SyncStatusTest extends TestCase
{
    /** @param array<string, string> $server */
    private function call(string $method, string $uri, ?string $role = null, array $server = []): Response
    {
        $kernel = new Kernel('test', true);
        $request = Request::create($uri, $method, [], [], [], $server);
        $session = new Session(new MockArraySessionStorage());
        if ($role !== null) {
            $session->set('user', ['id' => 1, 'role' => $role]);
        }
        $request->setSession($session);

        try {
            return $kernel->handle($request, 1, true);
        } finally {
            $kernel->shutdown();
        }
    }

    public function testVisitorGetsA401(): void
    {
        $fetch = ['HTTP_ACCEPT' => 'application/json'];
        $this->assertSame(401, $this->call('GET', '/api/sync/status', null, $fetch)->getStatusCode());
        $this->assertSame(401, $this->call('POST', '/api/sync/toggle', null, $fetch)->getStatusCode());
    }

    public function testEmployeeIsForbidden(): void
    {
        $this->assertSame(403, $this->call('GET', '/api/sync/status', 'employee')->getStatusCode());
        $this->assertSame(403, $this->call('POST', '/api/sync/toggle', 'employee')->getStatusCode());
    }

    public function testStatusDoesNothingWhenNoRemoteIsConfigured(): void
    {
        $response = $this->call('GET', '/api/sync/status', 'admin');

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(['configured' => false], json_decode((string) $response->getContent(), true));
    }

    public function testToggleNeedsAToken(): void
    {
        $this->assertSame(403, $this->call('POST', '/api/sync/toggle', 'admin', ['CONTENT_TYPE' => 'application/json'])->getStatusCode());
    }

    private function listenerWrites(?bool $syncActive): int
    {
        $cache = new ArrayAdapter();
        if ($syncActive !== null) {
            $cache->get('database_auto_sync_active', static fn (): bool => $syncActive);
        }
        $calls = 0;
        $connection = $this->createMock(Connection::class);
        $connection->method('insert')->willReturnCallback(static function () use (&$calls): int {
            ++$calls;

            return 1;
        });
        $manager = $this->createMock(EntityManagerInterface::class);
        $manager->method('getConnection')->willReturn($connection);

        (new SyncLogSubscriber($cache, new NullLogger()))->postPersist(new PostPersistEventArgs(new Transaction(), $manager));

        return $calls;
    }

    public function testChangesAreOnlyLoggedWhileSyncIsOn(): void
    {
        $this->assertSame(0, $this->listenerWrites(null), 'never switched on');
        $this->assertSame(0, $this->listenerWrites(false), 'switched off');
        $this->assertSame(1, $this->listenerWrites(true), 'switched on');
    }
}
