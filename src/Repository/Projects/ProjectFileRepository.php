<?php

namespace App\Repository\Projects;

use App\Entity\Projects\ProjectFile;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ProjectFile>
 */
class ProjectFileRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ProjectFile::class);
    }

    /**
     * @return ProjectFile[]
     */
    public function findForProject(int $projectId): array
    {
        if ($projectId <= 0) {
            return [];
        }

        return $this->createQueryBuilder('pf')
            ->andWhere('pf.project_id = :projectId')
            ->setParameter('projectId', $projectId)
            ->orderBy('pf.created_at', 'DESC')
            ->addOrderBy('pf.id', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
