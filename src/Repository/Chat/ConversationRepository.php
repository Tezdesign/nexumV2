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

    /**
     * Direct messages of a user as conversation id => the other person's user id, newest activity first.
     *
     * A dm_key is `<smaller id>_<larger id>`, so the user is either at the start or at the end of it. The two
     * LIKE patterns let the database return only that user's rows (the old query returned every DM of
     * every user and filtered them in PHP). `!` is the escape character so the `_` is not a wildcard.
     *
     * @return array<int, int>
     */
    public function findDmPartnersForUser(int $userId): array
    {
        $rows = $this->createQueryBuilder('c')
            ->select('c.id AS id', 'c.dm_key AS dmKey')
            ->andWhere("c.dm_key LIKE :asFirst ESCAPE '!' OR c.dm_key LIKE :asSecond ESCAPE '!'")
            ->setParameter('asFirst', $userId . '!_%')
            ->setParameter('asSecond', '%!_' . $userId)
            ->orderBy('c.last_message_at', 'DESC')
            ->addOrderBy('c.created_at', 'DESC')
            ->getQuery()
            ->getArrayResult();

        $partners = [];
        foreach ($rows as $row) {
            // Re-check the exact format: the patterns also match odd keys such as `7_12_9`.
            if (preg_match('/^(\d+)_(\d+)$/', (string) $row['dmKey'], $m) !== 1) {
                continue;
            }

            [$first, $second] = [(int) $m[1], (int) $m[2]];
            if ($first !== $userId && $second !== $userId) {
                continue;
            }

            $partners[(int) $row['id']] = $first === $userId ? $second : $first;
        }

        return $partners;
    }

    /**
     * @return int[]
     */
    public function findDmConversationIdsForUser(int $userId): array
    {
        return array_keys($this->findDmPartnersForUser($userId));
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
