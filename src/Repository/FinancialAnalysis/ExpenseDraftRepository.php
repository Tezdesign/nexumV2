<?php

namespace App\Repository\FinancialAnalysis;

use App\Entity\FinancialAnalysis\ExpenseDraft;
use App\Entity\Projects\Project;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ExpenseDraft>
 */
class ExpenseDraftRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ExpenseDraft::class);
    }

    public function save(ExpenseDraft $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * @return ExpenseDraft[] Returns an array of ExpenseDraft objects for a specific project
     */
    public function findByProject(Project $project): array
    {
        return $this->createQueryBuilder('e')
            ->join('e.project_budget_related', 'pb')
            ->andWhere('pb.project = :project')
            ->setParameter('project', $project)
            ->orderBy('e.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return array<int, ExpenseDraft>
     */
    public function findRecentDuplicates(int $budgetId, float $amount): array
    {
        $oneWeekAgo = new \DateTimeImmutable('-7 days');

        return $this->createQueryBuilder('e')
            ->join('e.project_budget_related', 'pb')
            ->where('pb.id = :budgetId')
            ->andWhere('e.amount = :amount')
            ->andWhere('e.createdAt >= :dateLimit') // Assuming you have a createdAt timestamp
            ->setParameter('budgetId', $budgetId)
            ->setParameter('amount', $amount)
            ->setParameter('dateLimit', $oneWeekAgo)
            ->getQuery()
            ->getResult();
    }

    public function approveDraft(ExpenseDraft $draft): void
    {
        $draft->setStatus('APPROVED');
        
        $evalData = $draft->getEvalData() ?? [];
        $evalData['final_decision'] = 'APPROVED';
        $draft->setEvalData($evalData);

        $this->getEntityManager()->persist($draft);
        $this->getEntityManager()->flush();
    }

    public function rejectDraft(ExpenseDraft $draft, string $reason, int $userId): void
    {
        $draft->setStatus('REJECTED');
        
        $evalData = $draft->getEvalData() ?? [];
        $evalData['final_decision'] = 'REJECTED';
        $evalData['rejection_data'] = [
            'reason' => $reason ?: 'Rejected by consultant.',
            'rejected_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
            'rejected_by' => $userId,
        ];
        $draft->setEvalData($evalData);

        $this->getEntityManager()->persist($draft);
        $this->getEntityManager()->flush();
    }

    public function revertDraft(ExpenseDraft $draft): void
    {
        $draft->setStatus('FLAGGED');
        
        $evalData = $draft->getEvalData() ?? [];
        $evalData['final_decision'] = 'FLAGGED';
        unset($evalData['rejection_data']);
        $draft->setEvalData($evalData);

        $this->getEntityManager()->persist($draft);
        $this->getEntityManager()->flush();
    }

    public function remove(ExpenseDraft $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}
