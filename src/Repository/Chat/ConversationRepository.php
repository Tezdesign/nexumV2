<?php

namespace App\Repository\Chat;

use App\Entity\Chat\Conversation;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Conversation>
 */
class ConversationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Conversation::class);
    }

    /**
     * @param int[] $ids
     *
     * @return Conversation[]
     */
    public function findByIds(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        return $this->createQueryBuilder('c')
            ->andWhere('c.id IN (:ids)')
            ->setParameter('ids', $ids)
            ->getQuery()
            ->getResult();
    }

    public function findByDmKey(string $dmKey): ?Conversation
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.dm_key = :dmKey')
            ->setParameter('dmKey', $dmKey)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Find an existing DM conversation between two users (order-independent).
     * For users 43 and 45, checks for both "43_45" and "45_43" keys.
     */
    public function findExistingDM(int $userId1, int $userId2): ?Conversation
    {
        // Generate both possible dm_key combinations
        $dmKey1 = min($userId1, $userId2) . '_' . max($userId1, $userId2);
        $dmKey2 = max($userId1, $userId2) . '_' . min($userId1, $userId2);

        return $this->createQueryBuilder('c')
            ->andWhere('c.dm_key IN (:dmKeys)')
            ->setParameter('dmKeys', [$dmKey1, $dmKey2])
            ->getQuery()
            ->getOneOrNullResult();
    }

    //    /**
    //     * @return Conversation[] Returns an array of Conversation objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('c')
    //            ->andWhere('c.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('c.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?Conversation
    //    {
    //        return $this->createQueryBuilder('c')
    //            ->andWhere('c.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
