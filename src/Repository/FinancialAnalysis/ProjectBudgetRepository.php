<?php

namespace App\Repository\FinancialAnalysis;

use App\Entity\FinancialAnalysis\ProjectBudget;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ProjectBudget>
 */
class ProjectBudgetRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ProjectBudget::class);
    }

    /**
     * @return ProjectBudget[] Returns an array of ProjectBudget objects within the FY scope
     */
    public function findByFiscalYearScope(\DateTimeInterface $startDate, \DateTimeInterface $endDate): array
    {
        return $this->createQueryBuilder('pb')
            ->andWhere('pb.dueDate >= :start')
            ->andWhere('pb.dueDate <= :end')
            ->setParameter('start', $startDate->format('Y-m-d'))
            ->setParameter('end', $endDate->format('Y-m-d'))
            ->orderBy('pb.dueDate', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Calculates the total allocated budgets and total expenses for all projects within a FY scope
     */
    public function getTotalsForFiscalYear(\DateTimeInterface $startDate, \DateTimeInterface $endDate): array
    {
        $result = $this->createQueryBuilder('pb')
            ->select('SUM(pb.total_budget) as totalAllocated', 'SUM(pb.actualSpend) as totalExpenses')
            ->andWhere('pb.dueDate >= :start')
            ->andWhere('pb.dueDate <= :end')
            ->setParameter('start', $startDate->format('Y-m-d'))
            ->setParameter('end', $endDate->format('Y-m-d'))
            ->getQuery()
            ->getSingleResult();

        return [
            'allocated' => $result['totalAllocated'] ? (float) $result['totalAllocated'] : 0.0,
            'expenses' => $result['totalExpenses'] ? (float) $result['totalExpenses'] : 0.0,
        ];
    }

    public function save(ProjectBudget $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(ProjectBudget $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}
