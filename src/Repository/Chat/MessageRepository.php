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
    public function findByConversationOrdered(int $conversationId): array
    {
        return $this->createQueryBuilder('m')
            ->andWhere('m.conversation_id = :conversationId')
            ->setParameter('conversationId', $conversationId)
            ->orderBy('m.created_at', 'ASC')
            ->addOrderBy('m.id', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return Message[]
     */
    public function findLastByConversationOrdered(int $conversationId, int $limit = 10): array
    {
        $safeLimit = max(1, $limit);

        $messages = $this->createQueryBuilder('m')
            ->andWhere('m.conversation_id = :conversationId')
            ->setParameter('conversationId', $conversationId)
            ->orderBy('m.created_at', 'DESC')
            ->addOrderBy('m.id', 'DESC')
            ->setMaxResults($safeLimit)
            ->getQuery()
            ->getResult();

        return array_reverse($messages);
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

    public function findLatestMessageByConversationId(int $conversationId): ?Message
    {
        return $this->createQueryBuilder('m')
            ->andWhere('m.conversation_id = :conversationId')
            ->setParameter('conversationId', $conversationId)
            ->orderBy('m.created_at', 'DESC')
            ->addOrderBy('m.id', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function countUnreadMessages(int $conversationId, ?int $lastReadMessageId, int $currentUserId): int
    {
        $queryBuilder = $this->createQueryBuilder('m')
            ->select('COUNT(m.id)')
            ->andWhere('m.conversation_id = :conversationId')
            ->andWhere('m.sender_id != :currentUserId')
            ->setParameter('conversationId', $conversationId)
            ->setParameter('currentUserId', $currentUserId);

        if ($lastReadMessageId !== null) {
            $queryBuilder
                ->andWhere('m.id > :lastReadMessageId')
                ->setParameter('lastReadMessageId', $lastReadMessageId);
        }

        return (int) $queryBuilder->getQuery()->getSingleScalarResult();
    }

    /**
     * Find recent CALL messages after a specific message ID
     * @return Message[]
     */
    public function findRecentCallMessages(int $conversationId, int $lastMessageId, int $limit = 20): array
    {
        return $this->createQueryBuilder('m')
            ->andWhere('m.conversation_id = :conversationId')
            ->andWhere('m.id > :lastMessageId')
            ->andWhere('m.kind = :kind')
            ->setParameter('conversationId', $conversationId)
            ->setParameter('lastMessageId', $lastMessageId)
            ->setParameter('kind', 'CALL')
            ->orderBy('m.id', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * @param int[] $conversationIds
     *
     * @return Message[]
     */
    public function findRecentCallMessagesForConversations(array $conversationIds, int $lastMessageId, int $limit = 50): array
    {
        if ($conversationIds === []) {
            return [];
        }

        return $this->createQueryBuilder('m')
            ->andWhere('m.conversation_id IN (:conversationIds)')
            ->andWhere('m.id > :lastMessageId')
            ->andWhere('m.kind = :kind')
            ->setParameter('conversationIds', array_values(array_unique($conversationIds)))
            ->setParameter('lastMessageId', max(0, $lastMessageId))
            ->setParameter('kind', 'CALL')
            ->orderBy('m.id', 'ASC')
            ->setMaxResults(max(1, $limit))
            ->getQuery()
            ->getResult();
    }

    /**
     * @param int[] $conversationIds
     */
    public function findLatestCallMessageIdForConversations(array $conversationIds): int
    {
        if ($conversationIds === []) {
            return 0;
        }

        return (int) $this->createQueryBuilder('m')
            ->select('COALESCE(MAX(m.id), 0)')
            ->andWhere('m.conversation_id IN (:conversationIds)')
            ->andWhere('m.kind = :kind')
            ->setParameter('conversationIds', array_values(array_unique($conversationIds)))
            ->setParameter('kind', 'CALL')
            ->getQuery()
            ->getSingleScalarResult();
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
