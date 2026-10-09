<?php

namespace App\Tests\Repository;

use App\Entity\Chat\Conversation;
use App\Repository\Chat\ConversationRepository;
use Doctrine\DBAL\Configuration as DbalConfiguration;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Logging\Middleware as LoggingMiddleware;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;

final class ConversationRepositoryTest extends TestCase
{
    private EntityManager $em;
    private ConversationRepository $repository;
    private object $queryCounter;

    protected function setUp(): void
    {
        $config = ORMSetup::createAttributeMetadataConfiguration([dirname(__DIR__, 2).'/src/Entity'], true);

        // Counts every SQL statement the connection runs.
        $counter = new class extends AbstractLogger {
            public int $statements = 0;

            public function log($level, string|\Stringable $message, array $context = []): void
            {
                if (str_starts_with((string) $message, 'Executing')) {
                    ++$this->statements;
                }
            }
        };
        $this->queryCounter = $counter;
        $dbalConfig = new DbalConfiguration();
        $dbalConfig->setMiddlewares([new LoggingMiddleware($counter)]);

        $this->em = new EntityManager(DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true], $dbalConfig), $config);
        (new SchemaTool($this->em))->createSchema([$this->em->getClassMetadata(Conversation::class)]);

        $registry = $this->createMock(ManagerRegistry::class);
        $registry->method('getManagerForClass')->willReturn($this->em);
        $this->repository = new ConversationRepository($registry);
    }

    /** @return array<string, int> dm_key => conversation id */
    private function seed(string ...$dmKeys): array
    {
        $ids = [];
        foreach ($dmKeys as $key) {
            $dm = (new Conversation())->setType('DM')->setDmKey($key)->setCreatedAt(new \DateTime());
            $this->em->persist($dm);
            $this->em->flush();
            $ids[$key] = (int) $dm->getId();
        }

        return $ids;
    }

    public function testOnlyTheUsersOwnDirectMessagesAreReturnedWithTheOtherPerson(): void
    {
        // 7 is at the start of one key and at the end of another. The others only contain the digit 7 or the user id.
        $ids = $this->seed('7_12', '3_7', '17_30', '70_71', '4_27', '1_2', '7_1_2');

        $partners = $this->repository->findDmPartnersForUser(7);

        $this->assertSame([$ids['7_12'] => 12, $ids['3_7'] => 3], $partners);
    }

    public function testConversationIdListIsDerivedFromTheSameQuery(): void
    {
        $ids = $this->seed('7_12', '3_7', '5_9');

        $found = $this->repository->findDmConversationIdsForUser(7);
        sort($found);

        $this->assertSame([$ids['7_12'], $ids['3_7']], $found);
    }

    public function testGroupsWithoutADmKeyAreIgnoredAndUnknownUsersGetNothing(): void
    {
        $group = (new Conversation())->setType('GROUP')->setTitle('Team')->setCreatedAt(new \DateTime());
        $this->em->persist($group);
        $this->em->flush();
        $this->seed('7_12');

        $this->assertSame([], $this->repository->findDmPartnersForUser(99));
    }

    public function testOneStatementServesAnyNumberOfDirectMessages(): void
    {
        $this->seed(...array_map(static fn (int $n): string => '7_'.(100 + $n), range(1, 40)));

        $before = $this->queryCounter->statements;
        $partners = $this->repository->findDmPartnersForUser(7);

        $this->assertCount(40, $partners);
        $this->assertSame(1, $this->queryCounter->statements - $before);
    }
}
