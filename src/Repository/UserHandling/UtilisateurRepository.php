<?php

namespace App\Repository\UserHandling;

use App\Entity\UserHandling\Utilisateur;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Utilisateur>
 */
class UtilisateurRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Utilisateur::class);
    }

    public function findFirstManagerOrFirst(): ?Utilisateur
    {
        $employee = $this->createQueryBuilder('u')
            ->andWhere('LOWER(u.role) LIKE :role')
            ->setParameter('role', '%employ%')
            ->orderBy('u.id', 'ASC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        if ($employee !== null) {
            return $employee;
        }

        $manager = $this->createQueryBuilder('u')
            ->andWhere('LOWER(u.role) LIKE :role')
            ->setParameter('role', '%manager%')
            ->orderBy('u.id', 'ASC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        if ($manager !== null) {
            return $manager;
        }

        return $this->createQueryBuilder('u')
            ->orderBy('u.id', 'ASC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * @param int[] $ids
     * @return array<int, Utilisateur> keyed by user id
     */
    public function findIndexedByIds(array $ids): array
    {
        $ids = array_values(array_unique(array_map('intval', array_filter($ids, static fn ($v) => $v !== null))));
        if ($ids === []) {
            return [];
        }

        $users = $this->createQueryBuilder('u')
            ->andWhere('u.id IN (:ids)')
            ->setParameter('ids', $ids)
            ->getQuery()
            ->getResult();

        $out = [];
        foreach ($users as $u) {
            $out[$u->getId()] = $u;
        }

        return $out;
    }

    //    /**
    //     * @return Utilisateur[] Returns an array of Utilisateur objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('u')
    //            ->andWhere('u.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('u.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?Utilisateur
    //    {
    //        return $this->createQueryBuilder('u')
    //            ->andWhere('u.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
