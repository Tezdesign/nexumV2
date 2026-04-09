<?php

namespace App\Repository\FinancialAnalysis;

use App\Entity\FinancialAnalysis\BudgetProfile;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<BudgetProfile>
 */
class BudgetProfileRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, BudgetProfile::class);
    }

    public function updateTotalExpenseDql(BudgetProfile $profile, float $totalExpense): void
    {
        $qb = $this->createQueryBuilder('bp');
        $qb->update()
            ->set('bp.total_expense', ':total_expense')
            ->where('bp.id = :id')
            ->setParameter('total_expense', $totalExpense)
            ->setParameter('id', $profile->getId())
            ->getQuery()
            ->execute();
    }

    //    /**
    //     * @return BudgetProfile[] Returns an array of BudgetProfile objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('b')
    //            ->andWhere('b.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('b.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?BudgetProfile
    //    {
    //        return $this->createQueryBuilder('b')
    //            ->andWhere('b.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
