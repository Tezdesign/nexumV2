<?php

namespace App\Repository;

use App\Entity\Participer;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Participer>
 */
class ParticiperRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Participer::class);
    }

    /**
     * @return array<string, int>
     */
    public function getGlobalStats(): array
    {
        $qb = $this->createQueryBuilder('p');

        return [
            'totalParticipations' => (int) $qb->select('COUNT(p.id)')->getQuery()->getSingleScalarResult(),

            'enCours' => (int) $this->createQueryBuilder('p')
                ->select('COUNT(p.id)')
                ->andWhere('p.statut = :statut')
                ->setParameter('statut', 'EN_COURS')
                ->getQuery()
                ->getSingleScalarResult(),

            'pretQuiz' => (int) $this->createQueryBuilder('p')
                ->select('COUNT(p.id)')
                ->andWhere('p.statut = :statut')
                ->setParameter('statut', 'PRET_QUIZ')
                ->getQuery()
                ->getSingleScalarResult(),

            'reussi' => (int) $this->createQueryBuilder('p')
                ->select('COUNT(p.id)')
                ->andWhere('p.statut = :statut')
                ->setParameter('statut', 'REUSSI')
                ->getQuery()
                ->getSingleScalarResult(),

            'echec' => (int) $this->createQueryBuilder('p')
                ->select('COUNT(p.id)')
                ->andWhere('p.statut = :statut')
                ->setParameter('statut', 'ECHEC')
                ->getQuery()
                ->getSingleScalarResult(),
        ];
    }

    public function getAverageProgression(): float
    {
        return (float) $this->createQueryBuilder('p')
            ->select('COALESCE(AVG(p.progression), 0)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * @return array<string, int>
     */
    public function getProgressBuckets(): array
    {
        return [
            '0-25' => (int) $this->countByRange(0, 25),
            '26-50' => (int) $this->countByRange(26, 50),
            '51-75' => (int) $this->countByRange(51, 75),
            '76-100' => (int) $this->countByRange(76, 100),
        ];
    }

    private function countByRange(int $min, int $max): int
    {
        return (int) $this->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->andWhere('p.progression BETWEEN :min AND :max')
            ->setParameter('min', $min)
            ->setParameter('max', $max)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getMonthlyInscriptions(): array
    {
        return $this->createQueryBuilder('p')
            ->select("DATE_FORMAT(p.dateInscription, '%Y-%m') AS month")
            ->addSelect('COUNT(p.id) AS total')
            ->groupBy('month')
            ->orderBy('month', 'ASC')
            ->getQuery()
            ->getArrayResult();
    }
}
