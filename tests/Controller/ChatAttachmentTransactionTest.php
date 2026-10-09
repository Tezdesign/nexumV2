<?php

namespace App\Tests\Controller;

use App\Controller\chat\MessageAttachmentController;
use App\Entity\Chat\Conversation;
use App\Entity\Chat\Message;
use App\Entity\Chat\MessageAttachment;
use App\Service\AuthService;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;
use App\Service\Chat\UserAvatarUrl;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class ChatAttachmentTransactionTest extends TestCase
{
    private EntityManager $em;
    private Conversation $conversation;

    protected function setUp(): void
    {
        $config = ORMSetup::createAttributeMetadataConfiguration([dirname(__DIR__, 2).'/src/Entity'], true);
        $this->em = new EntityManager(DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]), $config);
        (new SchemaTool($this->em))->createSchema([
            $this->em->getClassMetadata(Conversation::class),
            $this->em->getClassMetadata(Message::class),
            $this->em->getClassMetadata(MessageAttachment::class),
        ]);

        $this->conversation = (new Conversation())->setType('DM')->setDmKey('1_7')->setCreatedAt(new \DateTime());
        $this->em->persist($this->conversation);
        $this->em->flush();
    }

    private function save(int $size, string $data): mixed
    {
        $auth = $this->createMock(AuthService::class);
        $auth->method('getCurrentUserId')->willReturn(7);

        return (new \ReflectionMethod(MessageAttachmentController::class, 'saveAttachmentMessage'))->invoke(
            new MessageAttachmentController($auth, new UserAvatarUrl($this->createMock(UrlGeneratorInterface::class))),
            $this->em,
            $this->conversation,
            'report.txt',
            'report.txt',
            'text/plain',
            $size,
            $data,
        );
    }

    private function rows(string $class): int
    {
        return (int) $this->em->getConnection()->fetchOne('SELECT COUNT(*) FROM '.$this->em->getClassMetadata($class)->getTableName());
    }

    public function testMessageAndFileAreSavedTogether(): void
    {
        [$message, $attachment] = $this->save(5, 'hello');

        $this->assertSame(1, $this->rows(Message::class));
        $this->assertSame(1, $this->rows(MessageAttachment::class));
        $this->assertSame($message->getId(), $attachment->getMessageId());
        $this->assertSame($message->getId(), $this->conversation->getLastMessageId());
    }

    public function testRejectedFileLeavesNoMessageBehind(): void
    {
        try {
            $this->save(0, 'x');
            $this->fail('An empty size must be rejected.');
        } catch (\InvalidArgumentException $e) {
            $this->assertSame('Invalid file size.', $e->getMessage());
        }

        $this->assertSame(0, $this->rows(Message::class), 'the message must be rolled back with the file');
        $this->assertSame(0, $this->rows(MessageAttachment::class));
    }
}
