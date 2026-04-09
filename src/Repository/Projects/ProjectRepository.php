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

    /**
     * @param int[] $ids
     * @return array<int, Project> keyed by project id
     */
    public function findIndexedByIds(array $ids): array
    {
        $ids = array_values(array_unique(array_map('intval', array_filter($ids, static fn ($v) => $v !== null))));
        if ($ids === []) {
            return [];
        }

        $projects = $this->createQueryBuilder('p')
            ->andWhere('p.id IN (:ids)')
            ->setParameter('ids', $ids)
            ->getQuery()
            ->getResult();

        $out = [];
        foreach ($projects as $p) {
            if ($p->getId() !== null) {
                $out[$p->getId()] = $p;
            }
        }

        return $out;
    }

    /**
     * @return int[]
     */
    public function getProjectIdsForUser(int $userId): array
    {
        if ($userId <= 0) {
            return [];
        }

        $rows = $this->createQueryBuilder('p')
            ->select('p.id AS id')
            ->andWhere('p.created_by = :uid OR p.assigned_to = :uid')
            ->setParameter('uid', $userId)
            ->getQuery()
            ->getArrayResult();

        $ids = [];
        foreach ($rows as $row) {
            $id = (int) ($row['id'] ?? 0);
            if ($id > 0) {
                $ids[$id] = true;
            }
        }

        return array_map('intval', array_keys($ids));
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
