<?php

namespace App\Repository;

use App\Entity\Formation;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Formation>
 */
class FormationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Formation::class);
    }







    public function createAdminListQuery(array $filters = []): QueryBuilder
    {
        $qb = $this->createQueryBuilder('f')
            ->leftJoin('f.participations', 'p')
            ->leftJoin('f.resultats', 'r')
            ->addSelect('COUNT(DISTINCT p.id) AS HIDDEN participantsCount')
            ->addSelect('COUNT(DISTINCT r.id) AS HIDDEN resultsCount')
            ->groupBy('f.id');

        if (!empty($filters['q'])) {
            $qb->andWhere('f.titre LIKE :q OR f.description LIKE :q')
               ->setParameter('q', '%' . trim($filters['q']) . '%');
        }

        if (!empty($filters['hasVideos'])) {
            if ($filters['hasVideos'] === 'yes') {
                $qb->andWhere('f.video1 IS NOT NULL OR f.video2 IS NOT NULL OR f.video3 IS NOT NULL');
            } elseif ($filters['hasVideos'] === 'no') {
                $qb->andWhere('f.video1 IS NULL AND f.video2 IS NULL AND f.video3 IS NULL');
            }
        }

        if (!empty($filters['minParticipants']) && is_numeric($filters['minParticipants'])) {
            $qb->having('COUNT(DISTINCT p.id) >= :minParticipants')
               ->setParameter('minParticipants', (int) $filters['minParticipants']);
        }

        return $qb;
    }

    public function getTopFormationsStats(): array
    {
        return $this->createQueryBuilder('f')
            ->select('f.id, f.titre, COUNT(DISTINCT p.id) AS participants, COUNT(DISTINCT r.id) AS attempts')
            ->leftJoin('f.participations', 'p')
            ->leftJoin('f.resultats', 'r')
            ->groupBy('f.id')
            ->orderBy('participants', 'DESC')
            ->setMaxResults(6)
            ->getQuery()
            ->getArrayResult();
    }




    //    /**
    //     * @return Formation[] Returns an array of Formation objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('f')
    //            ->andWhere('f.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('f.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?Formation
    //    {
    //        return $this->createQueryBuilder('f')
    //            ->andWhere('f.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
