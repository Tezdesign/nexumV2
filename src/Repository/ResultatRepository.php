<?php

namespace App\Repository;

use App\Entity\Resultat;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Resultat>
 */
class ResultatRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Resultat::class);
    }

    /**
     * @return array{
     *   totalAttempts: int,
     *   successCount: int,
     *   failureCount: int,
     *   averagePercent: float|int,
     *   successRate: float|int,
     * }
     */
    public function getQuizStats(): array
    {
        $all = $this->findAll();

        $totalAttempts = count($all);
        $success = 0;
        $sumPercent = 0;

        foreach ($all as $resultat) {
            $percent = $resultat->getTotal() > 0
                ? ($resultat->getScore() / $resultat->getTotal()) * 100
                : 0;

            $sumPercent += $percent;

            if ($percent >= 60) {
                $success++;
            }
        }

        return [
            'totalAttempts' => $totalAttempts,
            'successCount' => $success,
            'failureCount' => $totalAttempts - $success,
            'averagePercent' => $totalAttempts > 0 ? round($sumPercent / $totalAttempts, 2) : 0,
            'successRate' => $totalAttempts > 0 ? round(($success / $totalAttempts) * 100, 2) : 0,
        ];
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function getAverageScoreByFormation(): array
    {
        return array_values($this->createQueryBuilder('r')
            ->select('f.titre AS formation')
            ->addSelect('AVG((r.score * 100.0) / NULLIF(r.total, 0)) AS avgPercent')
            ->join('r.formation', 'f')
            ->groupBy('f.id')
            ->orderBy('avgPercent', 'DESC')
            ->getQuery()
            ->getArrayResult());
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function getMonthlyAttempts(): array
    {
        return array_values($this->createQueryBuilder('r')
            ->select("DATE_FORMAT(r.datePassage, '%Y-%m') AS month")
            ->addSelect('COUNT(r.id) AS total')
            ->groupBy('month')
            ->orderBy('month', 'ASC')
            ->getQuery()
            ->getArrayResult());
    }
}