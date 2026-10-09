<?php

namespace App\Tests\Repository;

use App\Entity\Chat\Conversation;
use App\Entity\Chat\ConversationParticipant;
use App\Repository\Chat\ConversationParticipantRepository;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\TestCase;

final class ConversationParticipantRepositoryTest extends TestCase
{
    private EntityManager $em;
    private ConversationParticipantRepository $repository;
    private int $dmId;

    protected function setUp(): void
    {
        $config = ORMSetup::createAttributeMetadataConfiguration([dirname(__DIR__, 2).'/src/Entity'], true);
        $this->em = new EntityManager(DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]), $config);
        (new SchemaTool($this->em))->createSchema([
            $this->em->getClassMetadata(Conversation::class),
            $this->em->getClassMetadata(ConversationParticipant::class),
        ]);

        $registry = $this->createMock(ManagerRegistry::class);
        $registry->method('getManagerForClass')->willReturn($this->em);
        $this->repository = new ConversationParticipantRepository($registry);

        $dm = (new Conversation())->setType('DM')->setDmKey('1_7')->setCreatedAt(new \DateTime());
        $this->em->persist($dm);
        $this->em->flush();
        $this->dmId = (int) $dm->getId();

        foreach ([1, 7] as $userId) {
            $this->em->persist((new ConversationParticipant())
                ->setConversation_id($this->dmId)->setUser_id($userId)->setRole('member')
                ->setJoined_at(new \DateTime())->setLeft_at(null));
        }
        $this->em->flush();
    }

    public function testRemovingADmOnlyHidesItForThePersonWhoAskedAndKeepsTheOtherSide(): void
    {
        $this->repository->leaveDirectConversation($this->dmId, 1);

        $this->assertFalse($this->repository->isActiveParticipant($this->dmId, 1));
        $this->assertTrue($this->repository->isActiveParticipant($this->dmId, 7), 'the other person keeps access');
        $this->assertSame([], $this->repository->findConversationIdsForUser(1));
        $this->assertSame([$this->dmId], $this->repository->findConversationIdsForUser(7));
        $this->assertSame([$this->dmId], $this->repository->findLeftConversationIdsForUser(1));
        $this->assertSame(1, (int) $this->em->getConnection()->fetchOne('SELECT COUNT(*) FROM conversations'), 'the conversation and its messages are not deleted');
    }

    public function testANewMessageBringsTheConversationBackForEveryoneWhoRemovedIt(): void
    {
        $this->repository->leaveDirectConversation($this->dmId, 1);
        $this->em->clear();

        $this->repository->reactivateLeftParticipants($this->dmId);
        $this->em->clear();

        $this->assertTrue($this->repository->isActiveParticipant($this->dmId, 1));
        $this->assertSame([$this->dmId], $this->repository->findConversationIdsForUser(1));
    }

    public function testStartingTheChatAgainBringsBackOnlyThatUser(): void
    {
        $this->repository->leaveDirectConversation($this->dmId, 1);
        $this->repository->leaveDirectConversation($this->dmId, 7);
        $this->em->clear();

        $this->repository->reactivateLeftParticipants($this->dmId, 1);
        $this->em->clear();

        $this->assertTrue($this->repository->isActiveParticipant($this->dmId, 1));
        $this->assertFalse($this->repository->isActiveParticipant($this->dmId, 7));
    }

    public function testADmWithoutAParticipantRowCanStillBeRemoved(): void
    {
        $legacy = (new Conversation())->setType('DM')->setDmKey('3_9')->setCreatedAt(new \DateTime());
        $this->em->persist($legacy);
        $this->em->flush();

        $this->repository->leaveDirectConversation((int) $legacy->getId(), 3);

        $this->assertSame([(int) $legacy->getId()], $this->repository->findLeftConversationIdsForUser(3));
    }
}
