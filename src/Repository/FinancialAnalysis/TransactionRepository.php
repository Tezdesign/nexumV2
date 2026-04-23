<?php

namespace App\Repository\FinancialAnalysis;

use App\Entity\FinancialAnalysis\Transaction;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Transaction>
 */
class TransactionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Transaction::class);
    }

    public function save(Transaction $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function getTotalCostForProjectBudget(int $projectBudgetId): float
    {
        $result = $this->createQueryBuilder('t')
            ->select('SUM(t.cost) as totalCost')
            ->andWhere('t.projectBudget = :pbId')
            ->setParameter('pbId', $projectBudgetId)
            ->getQuery()
            ->getSingleScalarResult();

        return $result ? (float) $result : 0.0;
    }

    public function searchByReferenceOrDescriptionDql(int $projectBudgetId, string $searchTerm): array
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.projectBudget = :pbId')
            ->andWhere('t.reference LIKE :term OR t.description LIKE :term')
            ->setParameter('pbId', $projectBudgetId)
            ->setParameter('term', '%' . $searchTerm . '%')
            ->orderBy('t.date_stamp', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function updateTransactionDql(Transaction $transaction): void
    {
        $this->createQueryBuilder('t')
            ->update()
            ->set('t.reference', ':ref')
            ->set('t.cost', ':cost')
            ->set('t.date_stamp', ':date')
            ->set('t.expense_category', ':cat')
            ->set('t.description', ':desc')
            ->where('t.id = :id')
            ->setParameter('ref', $transaction->getReference())
            ->setParameter('cost', $transaction->getCost())
            ->setParameter('date', $transaction->getDateStamp() ? $transaction->getDateStamp()->format('Y-m-d') : null)
            ->setParameter('cat', $transaction->getExpenseCategory())
            ->setParameter('desc', $transaction->getDescription())
            ->setParameter('id', $transaction->getId())
            ->getQuery()
            ->execute();
    }

    public function bulkDeleteDql(array $ids): void
    {
        $this->createQueryBuilder('t')
            ->delete()
            ->where('t.id IN (:ids)')
            ->setParameter('ids', $ids)
            ->getQuery()
            ->execute();
    }

    public function deleteByProjectBudgetDql(int $pbId): void
    {
        $this->createQueryBuilder('t')
            ->delete()
            ->where('t.projectBudget = :pbId')
            ->setParameter('pbId', $pbId)
            ->getQuery()
            ->execute();
    }

    public function deleteByFiscalYearScopeDql(\DateTimeInterface $start, \DateTimeInterface $end): void
    {
        $this->getEntityManager()->createQuery('
            DELETE FROM App\Entity\FinancialAnalysis\Transaction t 
            WHERE t.projectBudget IN (
                SELECT pb.id FROM App\Entity\FinancialAnalysis\ProjectBudget pb 
                WHERE pb.dueDate >= :start AND pb.dueDate <= :end
            )
        ')
        ->setParameter('start', $start->format('Y-m-d'))
        ->setParameter('end', $end->format('Y-m-d'))
        ->execute();
    }

    /**
     * Aggregates transaction count and volume by month for a project.
     */
    public function getMonthlyAggregation(int $projectBudgetId): array
    {
        // We use SUBSTRING to extract YYYY-MM from the date. 
        // This works in many SQL engines (MySQL, SQLite, etc.) via DQL.
        $results = $this->createQueryBuilder('t')
            ->select("SUBSTRING(t.date_stamp, 1, 7) as month, COUNT(t.id) as txCount, SUM(t.cost) as txVolume")
            ->andWhere('t.projectBudget = :pbId')
            ->setParameter('pbId', $projectBudgetId)
            ->groupBy('month')
            ->orderBy('month', 'ASC')
            ->getQuery()
            ->getResult();

        $months = [];
        $counts = [];
        $volumes = [];

        foreach ($results as $row) {
            $months[] = $row['month'];
            $counts[] = (int) $row['txCount'];
            $volumes[] = (float) $row['txVolume'];
        }

        return [
            'months' => $months,
            'counts' => $counts,
            'volumes' => $volumes
        ];
    }

    /**
     * Aggregates transaction counts by category for a project.
     */
    public function getCategoryAggregation(int $projectBudgetId): array
    {
        $results = $this->createQueryBuilder('t')
            ->select("t.expense_category as category, COUNT(t.id) as txCount")
            ->andWhere('t.projectBudget = :pbId')
            ->setParameter('pbId', $projectBudgetId)
            ->groupBy('category')
            ->orderBy('txCount', 'DESC')
            ->getQuery()
            ->getResult();

        $labels = [];
        $counts = [];

        foreach ($results as $row) {
            $labels[] = $row['category'] ?: 'Uncategorized';
            $counts[] = (int) $row['txCount'];
        }

        return [
            'labels' => $labels,
            'counts' => $counts
        ];
    }

    /**
     * Calculates the average cost of all existing transactions for a budget.
     */
    public function getAverageTransactionCost(int $projectBudgetId): float
    {
        $result = $this->createQueryBuilder('t')
            ->select('AVG(t.cost) as avgCost')
            ->andWhere('t.projectBudget = :pbId')
            ->setParameter('pbId', $projectBudgetId)
            ->getQuery()
            ->getSingleScalarResult();

        return $result ? (float) $result : 0.0;
    }


    //    /**
    //     * @return Transaction[] Returns an array of Transaction objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('t')
    //            ->andWhere('t.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('t.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?Transaction
    //    {
    //        return $this->createQueryBuilder('t')
    //            ->andWhere('t.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
