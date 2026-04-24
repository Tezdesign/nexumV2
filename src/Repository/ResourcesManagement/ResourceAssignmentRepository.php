<?php

namespace App\Repository\ResourcesManagement;

use App\Entity\ResourcesManagement\ResourceAssignment;
use App\Entity\UserHandling\Utilisateur;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ResourceAssignment>
 */
class ResourceAssignmentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ResourceAssignment::class);
    }

    public function countPendingRequestsByUser(Utilisateur $user): int
    {
        return (int) $this->createQueryBuilder('ra')
            ->select('COUNT(ra.assignment_id)')
            ->andWhere('ra.utilisateur = :user')
            ->andWhere('ra.status = :status')
            ->setParameter('user', $user)
            ->setParameter('status', 'PENDING')
            ->getQuery()
            ->getSingleScalarResult();
    }

    //    /**
    //     * @return ResourceAssignment[] Returns an array of ResourceAssignment objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('r')
    //            ->andWhere('r.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('r.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?ResourceAssignment
    //    {
    //        return $this->createQueryBuilder('r')
    //            ->andWhere('r.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
