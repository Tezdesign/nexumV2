<?php

namespace App\Repository\Tasks;

use App\Entity\Tasks\Task;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Task>
 */
class TaskRepository extends ServiceEntityRepository
{
    private const COMPLETED_STATUSES = ['done', 'completed', 'complete', 'finished'];

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Task::class);
    }

    /**
     * "My Tasks" list for a given user, optionally filtered by query.
     *
     * @return Task[]
     */
    public function findForUser(int $userId, ?string $q = null): array
    {
        $qb = $this->createQueryBuilder('t')
            ->andWhere('t.assigned_to = :uid')
            ->setParameter('uid', $userId)
            ->orderBy('t.project_id', 'ASC')
            ->addOrderBy('t.due_date', 'ASC')
            ->addOrderBy('t.id', 'DESC');

        $q = $q !== null ? trim($q) : '';
        if ($q !== '') {
            $q = strtolower($q);
            $qb
                ->andWhere('(LOWER(t.title) LIKE :q OR LOWER(COALESCE(t.description, \'\')) LIKE :q)')
                ->setParameter('q', '%' . $q . '%');
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * @return Task[]
     */
    public function findForProject(int $projectId, int $limit = 0): array
    {
        $qb = $this->createQueryBuilder('t')
            ->andWhere('t.project_id = :pid')
            ->setParameter('pid', $projectId)
            ->orderBy('t.due_date', 'ASC')
            ->addOrderBy('t.id', 'DESC');

        if ($limit > 0) {
            $qb->setMaxResults($limit);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * @return array{total:int, completed:int, overdue:int}
     */
    public function getStatsForProject(int $projectId, \DateTimeInterface $today = new \DateTimeImmutable('today')): array
    {
        $statuses = self::COMPLETED_STATUSES;

        // DQL doesn't support binding an array directly into IN() of a LOWER() expression in all DBs.
        // We normalize by passing lowercase strings.
        $statuses = array_map('strval', $statuses);

        $qb = $this->createQueryBuilder('t')
            ->select('COUNT(t.id) AS total')
            ->addSelect(
                'SUM(CASE WHEN (t.status IS NOT NULL AND LOWER(t.status) IN (:completed)) THEN 1 ELSE 0 END) AS completed'
            )
            ->addSelect(
                'SUM(CASE WHEN (t.due_date IS NOT NULL AND t.due_date < :today AND (t.status IS NULL OR LOWER(t.status) NOT IN (:completed))) THEN 1 ELSE 0 END) AS overdue'
            )
            ->andWhere('t.project_id = :pid')
            ->setParameter('pid', $projectId)
            ->setParameter('today', \DateTimeImmutable::createFromInterface($today)->setTime(0, 0, 0))
            ->setParameter('completed', $statuses);

        /** @var array{total:string|int|null, completed:string|int|null, overdue:string|int|null}|null $row */
        $row = $qb->getQuery()->getOneOrNullResult();

        return [
            'total' => (int) ($row['total'] ?? 0),
            'completed' => (int) ($row['completed'] ?? 0),
            'overdue' => (int) ($row['overdue'] ?? 0),
        ];
    }

    //    /**
    //     * @return Task[] Returns an array of Task objects
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

    //    public function findOneBySomeField($value): ?Task
    //    {
    //        return $this->createQueryBuilder('t')
    //            ->andWhere('t.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
