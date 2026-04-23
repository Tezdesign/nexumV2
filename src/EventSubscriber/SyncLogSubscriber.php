<?php

namespace App\EventSubscriber;

use App\Entity\FinancialAnalysis\ExpenseDraft;
use App\Entity\FinancialAnalysis\Transaction;
use App\Entity\FinancialAnalysis\ProjectBudget;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Events;
use Doctrine\ORM\Event\PostPersistEventArgs;
use Doctrine\ORM\Event\PostUpdateEventArgs;
use Doctrine\ORM\Event\PreRemoveEventArgs;

#[AsDoctrineListener(event: Events::postPersist)]
#[AsDoctrineListener(event: Events::postUpdate)]
#[AsDoctrineListener(event: Events::preRemove)]
class SyncLogSubscriber
{
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
            // Log to file if the table doesn't exist or insert fails
            file_put_contents(__DIR__ . '/../../var/log/sync_listener_error.log', $e->getMessage() . PHP_EOL, FILE_APPEND);
        }
    }
}