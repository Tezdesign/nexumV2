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
