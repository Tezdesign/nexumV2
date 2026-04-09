<?php

namespace App\Repository\Chat;

use App\Entity\Chat\MessageAttachment;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<MessageAttachment>
 */
class MessageAttachmentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, MessageAttachment::class);
    }

    /**
     * @param int[] $messageIds
     *
     * @return MessageAttachment[]
     */
    public function findByMessageIds(array $messageIds): array
    {
        if ($messageIds === []) {
            return [];
        }

        return $this->createQueryBuilder('ma')
            ->andWhere('ma.message_id IN (:messageIds)')
            ->setParameter('messageIds', $messageIds)
            ->orderBy('ma.message_id', 'ASC')
            ->addOrderBy('ma.created_at', 'ASC')
            ->addOrderBy('ma.id', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return MessageAttachment[]
     */
    public function findByMessageId(int $messageId): array
    {
        return $this->createQueryBuilder('ma')
            ->andWhere('ma.message_id = :messageId')
            ->setParameter('messageId', $messageId)
            ->orderBy('ma.created_at', 'ASC')
            ->addOrderBy('ma.id', 'ASC')
            ->getQuery()
            ->getResult();
    }

    //    /**
    //     * @return MessageAttachment[] Returns an array of MessageAttachment objects
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

    //    public function findOneBySomeField($value): ?MessageAttachment
    //    {
    //        return $this->createQueryBuilder('m')
    //            ->andWhere('m.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
