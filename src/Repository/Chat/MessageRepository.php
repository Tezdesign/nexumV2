<?php

namespace App\Repository\Chat;

use App\Entity\Chat\Message;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Message>
 */
class MessageRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Message::class);
    }

    /**
     * @return Message[]
     */
    public function findByConversationOrdered(int $conversationId, int $limit = 200): array
    {
        return $this->createQueryBuilder('m')
            ->andWhere('m.conversation_id = :conversationId')
            ->setParameter('conversationId', $conversationId)
            ->orderBy('m.created_at', 'ASC')
            ->addOrderBy('m.id', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * @param int[] $conversationIds
     *
     * @return array<int, Message>
     */
    public function findLatestMessagesByConversationIds(array $conversationIds): array
    {
        if ($conversationIds === []) {
            return [];
        }

        $messages = $this->createQueryBuilder('m')
            ->andWhere('m.conversation_id IN (:conversationIds)')
            ->setParameter('conversationIds', $conversationIds)
            ->orderBy('m.conversation_id', 'ASC')
            ->addOrderBy('m.created_at', 'DESC')
            ->addOrderBy('m.id', 'DESC')
            ->getQuery()
            ->getResult();

        $latestByConversation = [];
        foreach ($messages as $message) {
            $conversationId = $message->getConversationId();
            if ($conversationId === null || isset($latestByConversation[$conversationId])) {
                continue;
            }

            $latestByConversation[$conversationId] = $message;
        }

        return $latestByConversation;
    }

    //    /**
    //     * @return Message[] Returns an array of Message objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('m')
    //            ->andWhere('m.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('m.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?Message
    //    {
    //        return $this->createQueryBuilder('m')
    //            ->andWhere('m.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
