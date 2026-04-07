<?php

namespace App\Repository\Projects;

use App\Entity\Projects\Project;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Project>
 */
class ProjectRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Project::class);
    }

    /**
     * @return Project[]
     */
    public function findForIndex(?string $q = null): array
    {
        $qb = $this->createQueryBuilder('p')
            ->orderBy('p.updated_at', 'DESC')
            ->addOrderBy('p.id', 'DESC');

        $q = $q !== null ? trim($q) : '';
        if ($q !== '') {
            $qb
                ->andWhere('(LOWER(p.name) LIKE :q OR LOWER(COALESCE(p.description, \'\')) LIKE :q)')
                ->setParameter('q', '%' . strtolower($q) . '%');
        }

        return $qb->getQuery()->getResult();
    }

    //    /**
    //     * @return Project[] Returns an array of Project objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('p')
    //            ->andWhere('p.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('p.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?Project
    //    {
    //        return $this->createQueryBuilder('p')
    //            ->andWhere('p.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
