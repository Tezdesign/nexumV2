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
     *
     * @return array{allocated: float, expenses: float}
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

    public function updateBudgetDql(ProjectBudget $budget): void
    {
        $qb = $this->createQueryBuilder('pb');
        $qb->update()
            ->set('pb.name', ':name')
            ->set('pb.project', ':project')
            ->set('pb.total_budget', ':total_budget')
            ->set('pb.status', ':status')
            ->set('pb.dueDate', ':dueDate')
            ->where('pb.id = :id')
            ->setParameter('name', $budget->getName())
            ->setParameter('project', $budget->getProject())
            ->setParameter('total_budget', $budget->getTotalBudget())
            ->setParameter('status', $budget->getStatus())
            ->setParameter('dueDate', $budget->getDueDate() ? $budget->getDueDate()->format('Y-m-d') : null)
            ->setParameter('id', $budget->getId())
            ->getQuery()
            ->execute();
    }

    public function updateActualSpendAndStatusDql(ProjectBudget $budget): void
    {
        $qb = $this->createQueryBuilder('pb');
        $qb->update()
            ->set('pb.actualSpend', ':actualSpend')
            ->set('pb.status', ':status')
            ->where('pb.id = :id')
            ->setParameter('actualSpend', $budget->getActualSpend())
            ->setParameter('status', $budget->getStatus())
            ->setParameter('id', $budget->getId())
            ->getQuery()
            ->execute();

        // Manually log to sync_log since DQL bypasses ORM event listeners
        try {
            $this->getEntityManager()->getConnection()->insert('sync_log', [
                'entity_class' => ProjectBudget::class,
                'entity_id' => $budget->getId(),
                'action_type' => 'UPDATE',
                'created_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable $e) {
            // Fail silently if table doesn't exist
        }
    }

    public function deleteProjectBudgetDql(int $id): void
    {
        $this->createQueryBuilder('pb')
            ->delete()
            ->where('pb.id = :id')
            ->setParameter('id', $id)
            ->getQuery()
            ->execute();
    }

    public function deleteByFiscalYearScopeDql(\DateTimeInterface $start, \DateTimeInterface $end): void
    {
        $this->createQueryBuilder('pb')
            ->delete()
            ->where('pb.dueDate >= :start')
            ->andWhere('pb.dueDate <= :end')
            ->setParameter('start', $start->format('Y-m-d'))
            ->setParameter('end', $end->format('Y-m-d'))
            ->getQuery()
            ->execute();
    }

    /**
     * Calculates the sum of total_budget and actualSpend for all budgets under the exact same project.
     *
     * @return array{allocated: float, spent: float}
     */
    public function getProjectBudgetsAggregates(int $projectId): array
    {
        $result = $this->createQueryBuilder('pb')
            ->select('SUM(pb.total_budget) as totalProjectAllocated', 'SUM(pb.actualSpend) as totalProjectSpent')
            ->andWhere('pb.project = :projectId')
            ->setParameter('projectId', $projectId)
            ->getQuery()
            ->getSingleResult();

        return [
            'allocated' => $result['totalProjectAllocated'] ? (float) $result['totalProjectAllocated'] : 0.0,
            'spent' => $result['totalProjectSpent'] ? (float) $result['totalProjectSpent'] : 0.0,
        ];
    }

    /**
     * Evaluates the spending rank of the current budget compared to sibling budgets in the same project.
     * Returns an array with ['rank' => X, 'totalBudgets' => Y]
     *
     * @return array{rank: int, totalBudgets: int}
     */
    public function getBudgetSpendingRank(int $projectId, float $currentSpend): array
    {
        // Total number of budgets in this project
        $totalBudgets = $this->createQueryBuilder('pb')
            ->select('COUNT(pb.id)')
            ->andWhere('pb.project = :projectId')
            ->setParameter('projectId', $projectId)
            ->getQuery()
            ->getSingleScalarResult();

        // How many budgets have an actualSpend GREATER than the current one?
        // (If 0 have greater spend, it's rank #1)
        $higherSpendCount = $this->createQueryBuilder('pb')
            ->select('COUNT(pb.id)')
            ->andWhere('pb.project = :projectId')
            ->andWhere('pb.actualSpend > :currentSpend')
            ->setParameter('projectId', $projectId)
            ->setParameter('currentSpend', $currentSpend)
            ->getQuery()
            ->getSingleScalarResult();

        return [
            'rank' => ((int) $higherSpendCount) + 1,
            'totalBudgets' => (int) $totalBudgets
        ];
    }

    /**
     * Gets the number of unique projects involved in the specified fiscal year.
     */
    public function getUniqueProjectCountForFY(\DateTimeInterface $start, \DateTimeInterface $end): int
    {
        return (int) $this->createQueryBuilder('pb')
            ->select('COUNT(DISTINCT pb.project)')
            ->where('pb.dueDate >= :start')
            ->andWhere('pb.dueDate <= :end')
            ->setParameter('start', $start->format('Y-m-d'))
            ->setParameter('end', $end->format('Y-m-d'))
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Gets the project name with the highest number of budgets in the specified fiscal year.
     *
     * @return array{projectName: string, budgetCount: int|string}
     */
    public function getProjectWithMostBudgetsForFY(\DateTimeInterface $start, \DateTimeInterface $end): array
    {
        $result = $this->createQueryBuilder('pb')
            ->select('p.name as projectName, COUNT(pb.id) as budgetCount')
            ->join('pb.project', 'p')
            ->where('pb.dueDate >= :start')
            ->andWhere('pb.dueDate <= :end')
            ->setParameter('start', $start->format('Y-m-d'))
            ->setParameter('end', $end->format('Y-m-d'))
            ->groupBy('p.id')
            ->orderBy('budgetCount', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
            
        return $result ?: ['projectName' => 'N/A', 'budgetCount' => 0];
    }
}

