<?php

namespace App\Repository\Projects;

use App\Entity\Projects\ProjectAssignment;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ProjectAssignment>
 */
class ProjectAssignmentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ProjectAssignment::class);
    }

    /**
     * @param int[] $projectIds
     * @return array<int, int[]> projectId => [userId, ...]
     */
    public function getUserIdsByProjectIds(array $projectIds): array
    {
        $projectIds = array_values(array_unique(array_map('intval', array_filter($projectIds, static fn ($v) => $v !== null))));
        if ($projectIds === []) {
            return [];
        }

        $rows = $this->createQueryBuilder('pa')
            ->select('pa.project_id AS project_id, pa.user_id AS user_id')
            ->andWhere('pa.project_id IN (:ids)')
            ->setParameter('ids', $projectIds)
            ->getQuery()
            ->getArrayResult();

        $out = [];
        foreach ($rows as $r) {
            $pid = (int) ($r['project_id'] ?? 0);
            $uid = (int) ($r['user_id'] ?? 0);
            if ($pid <= 0 || $uid <= 0) {
                continue;
            }
            $out[$pid] ??= [];
            $out[$pid][$uid] = true; // set semantics for dedupe
        }

        foreach ($out as $pid => $set) {
            $out[$pid] = array_map('intval', array_keys($set));
        }

        return $out;
    }

    /**
     * @return int[]
     */
    public function getUserIdsByProjectId(int $projectId): array
    {
        $map = $this->getUserIdsByProjectIds([$projectId]);
        return $map[$projectId] ?? [];
    }

    /**
     * @return int[]
     */
    public function getProjectIdsByUserId(int $userId): array
    {
        if ($userId <= 0) {
            return [];
        }

        $rows = $this->createQueryBuilder('pa')
            ->select('pa.project_id AS project_id')
            ->andWhere('pa.user_id = :uid')
            ->setParameter('uid', $userId)
            ->getQuery()
            ->getArrayResult();

        $ids = [];
        foreach ($rows as $row) {
            $pid = (int) ($row['project_id'] ?? 0);
            if ($pid > 0) {
                $ids[$pid] = true;
            }
        }

        return array_map('intval', array_keys($ids));
    }

    //    /**
    //     * @return ProjectAssignment[] Returns an array of ProjectAssignment objects
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

    //    public function findOneBySomeField($value): ?ProjectAssignment
    //    {
    //        return $this->createQueryBuilder('p')
    //            ->andWhere('p.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
