<?php

namespace App\EventSubscriber;

use App\Entity\FinancialAnalysis\ExpenseDraft;
use App\Entity\FinancialAnalysis\Transaction;
use App\Entity\FinancialAnalysis\ProjectBudget;
use App\Service\DatabaseHealthService;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Events;
use Doctrine\ORM\Event\PostPersistEventArgs;
use Doctrine\ORM\Event\PostUpdateEventArgs;
use Doctrine\ORM\Event\PreRemoveEventArgs;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\Cache\CacheInterface;

#[AsDoctrineListener(event: Events::postPersist)]
#[AsDoctrineListener(event: Events::postUpdate)]
#[AsDoctrineListener(event: Events::preRemove)]
class SyncLogSubscriber
{
    public function __construct(
        private readonly CacheInterface $cache,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function postPersist(PostPersistEventArgs $args): void
    {
        $this->logAction($args->getObject(), $args->getObjectManager(), 'INSERT');
    }

    public function postUpdate(PostUpdateEventArgs $args): void
    {
        $this->logAction($args->getObject(), $args->getObjectManager(), 'UPDATE');
    }

    public function preRemove(PreRemoveEventArgs $args): void
    {
        $this->logAction($args->getObject(), $args->getObjectManager(), 'DELETE');
    }

    private function logAction(object $entity, \Doctrine\Persistence\ObjectManager $manager, string $actionType): void
    {
        // We only care about syncing specific entities for now
        if (!$entity instanceof ExpenseDraft && !$entity instanceof Transaction && !$entity instanceof ProjectBudget) {
            return;
        }

        // Changes are only worth logging while sync is switched on; otherwise the table would grow with no reader.
        /** @var bool $active */
        $active = $this->cache->get(DatabaseHealthService::ACTIVE_KEY, static fn (): bool => false);
        if (!$active) {
            return;
        }

        $conn = $manager->getConnection();
        
        // Use raw SQL to prevent recursive flushes and ensure instant logging
        try {
            $conn->insert('sync_log', [
                'entity_class' => get_class($entity),
                'entity_id' => $entity->getId(),
                'action_type' => $actionType,
                'created_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable $e) {
            $this->logger->error('sync_log insert failed', ['exception' => $e]);
        }
    }
}