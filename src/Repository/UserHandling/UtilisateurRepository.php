<?php

namespace App\Repository\UserHandling;

use App\Entity\UserHandling\Utilisateur;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Utilisateur>
 */
class UtilisateurRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Utilisateur::class);
    }

    private function applyNonAdminFilter(QueryBuilder $qb, string $alias = 'u'): void
    {
        $qb
            ->andWhere(sprintf('LOWER(%s.role) NOT LIKE :adminRole', $alias))
            ->setParameter('adminRole', '%admin%');
    }

    public function findFirstManagerOrFirst(): ?Utilisateur
    {
        $employee = $this->createQueryBuilder('u')
            ->andWhere('LOWER(u.role) LIKE :role')
            ->setParameter('role', '%employ%')
            ->orderBy('u.id', 'ASC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        if ($employee !== null) {
            return $employee;
        }

        $manager = $this->createQueryBuilder('u')
            ->andWhere('LOWER(u.role) LIKE :role')
            ->setParameter('role', '%manager%')
            ->orderBy('u.id', 'ASC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        if ($manager !== null) {
            return $manager;
        }

        return $this->createQueryBuilder('u')
            ->orderBy('u.id', 'ASC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * @param int[] $ids
     * @return array<int, Utilisateur> keyed by user id
     */
    public function findIndexedByIds(array $ids): array
    {
        $ids = array_values(array_unique(array_map('intval', $ids)));
        if ($ids === []) {
            return [];
        }

        $users = $this->createQueryBuilder('u')
            ->andWhere('u.id IN (:ids)')
            ->setParameter('ids', $ids)
            ->getQuery()
            ->getResult();

        $out = [];
        foreach ($users as $u) {
            $out[$u->getId()] = $u;
        }

        return $out;
    }

    /**
     * @param int[] $ids
     * @return array<int, Utilisateur> keyed by user id
     */
    public function findNonAdminIndexedByIds(array $ids): array
    {
        $ids = array_values(array_unique(array_map('intval', $ids)));
        if ($ids === []) {
            return [];
        }

        $qb = $this->createQueryBuilder('u')
            ->andWhere('u.id IN (:ids)')
            ->setParameter('ids', $ids);
        $this->applyNonAdminFilter($qb, 'u');

        $users = $qb->getQuery()->getResult();

        $out = [];
        foreach ($users as $u) {
            $out[$u->getId()] = $u;
        }

        return $out;
    }

    /**
     * @return Utilisateur[]
     */
    public function findNonAdminUsers(): array
    {
        $qb = $this->createQueryBuilder('u')
            ->orderBy('u.role', 'ASC')
            ->addOrderBy('u.prenom', 'ASC')
            ->addOrderBy('u.nom', 'ASC');
        $this->applyNonAdminFilter($qb, 'u');

        return $qb->getQuery()->getResult();
    }

    /**
     * @return Utilisateur[]
     */
    public function findManagerUsers(): array
    {
        $qb = $this->createQueryBuilder('u')
            ->orderBy('u.prenom', 'ASC')
            ->addOrderBy('u.nom', 'ASC');
        $this->applyNonAdminFilter($qb, 'u');
        $qb
            ->andWhere('LOWER(u.role) LIKE :managerRole')
            ->setParameter('managerRole', '%manager%');

        return $qb->getQuery()->getResult();
    }

    //    /**
    //     * @return Utilisateur[] Returns an array of Utilisateur objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('u')
    //            ->andWhere('u.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('u.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    public function findByEmail(string $email): ?Utilisateur
    {
        return $this->createQueryBuilder('u')
            ->andWhere('u.email = :email')
            ->setParameter('email', $email)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function existsOtherUserWithEmail(string $email, int $excludeUserId): bool
    {
        $other = $this->findByEmail($email);

        return $other !== null && $other->getId() !== $excludeUserId;
    }

    /**
     * @return array{total: int, active: int, pending: int, admins: int}
     */
    public function getAdminUserStats(): array
    {
        $total = (int) $this->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            ->getQuery()
            ->getSingleScalarResult();

        $active = (int) $this->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            ->where('LOWER(u.statut) = :s')
            ->setParameter('s', 'active')
            ->getQuery()
            ->getSingleScalarResult();

        $pending = (int) $this->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            ->where('LOWER(u.statut) = :s')
            ->setParameter('s', 'pending')
            ->getQuery()
            ->getSingleScalarResult();

        $admins = (int) $this->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            ->where('LOWER(u.role) = :admin OR LOWER(u.role) = :administrator')
            ->setParameter('admin', 'admin')
            ->setParameter('administrator', 'administrator')
            ->getQuery()
            ->getSingleScalarResult();

        return [
            'total' => $total,
            'active' => $active,
            'pending' => $pending,
            'admins' => $admins,
        ];
    }

    /**
     * @return array{labels: list<string>, values: list<int>}
     */
    public function getChartDataByRole(): array
    {
        $rows = $this->createQueryBuilder('u')
            ->select('u.role AS label, COUNT(u.id) AS c')
            ->groupBy('u.role')
            ->getQuery()
            ->getArrayResult();

        if ($rows === []) {
            return ['labels' => ['—'], 'values' => [0]];
        }

        usort($rows, static fn (array $a, array $b): int => (int) $b['c'] <=> (int) $a['c']);

        return [
            'labels' => array_map(static fn (array $r) => (string) ($r['label'] ?? '?'), $rows),
            'values' => array_map(static fn (array $r) => (int) $r['c'], $rows),
        ];
    }

    /**
     * @return array{labels: list<string>, values: list<int>}
     */
    public function getChartDataByStatut(): array
    {
        $rows = $this->createQueryBuilder('u')
            ->select('u.statut AS label, COUNT(u.id) AS c')
            ->groupBy('u.statut')
            ->getQuery()
            ->getArrayResult();

        if ($rows === []) {
            return ['labels' => ['—'], 'values' => [0]];
        }

        usort($rows, static fn (array $a, array $b): int => (int) $b['c'] <=> (int) $a['c']);

        return [
            'labels' => array_map(static fn (array $r) => (string) ($r['label'] ?? '?'), $rows),
            'values' => array_map(static fn (array $r) => (int) $r['c'], $rows),
        ];
    }

    /**
     * @return list<array<string, mixed>> Rows without imagelink blob (safe for listing).
     */
    public function findForAdminListing(?string $search = null, string $sort = 'id', string $direction = 'ASC'): array
    {
        $allowedSort = [
            'id' => 'u.id',
            'nom' => 'u.nom',
            'prenom' => 'u.prenom',
            'email' => 'u.email',
            'role' => 'u.role',
            'statut' => 'u.statut',
            'departement' => 'u.departement',
            'date_inscription' => 'u.date_inscription',
        ];
        $orderExpr = $allowedSort[$sort] ?? 'u.id';
        $direction = strtoupper($direction) === 'DESC' ? 'DESC' : 'ASC';

        $qb = $this->createQueryBuilder('u')
            ->select([
                'u.id AS id',
                'u.nom AS nom',
                'u.prenom AS prenom',
                'u.email AS email',
                'u.telephone AS telephone',
                'u.role AS role',
                'u.departement AS departement',
                'u.statut AS statut',
                'u.date_inscription AS date_inscription',
            ]);

        if ($search !== null && $search !== '') {
            $term = strtolower(trim($search));
            $term = str_replace(['%', '_', '\\'], '', $term);
            if ($term !== '') {
                $like = '%'.$term.'%';
                $qb->andWhere($qb->expr()->orX(
                    'LOWER(u.nom) LIKE :q',
                    'LOWER(u.prenom) LIKE :q',
                    'LOWER(u.email) LIKE :q',
                    'LOWER(COALESCE(u.telephone, \'\')) LIKE :q',
                    'LOWER(COALESCE(u.departement, \'\')) LIKE :q',
                    'LOWER(u.role) LIKE :q',
                    'LOWER(u.statut) LIKE :q',
                ))
                    ->setParameter('q', $like);
            }
        }

        $qb->orderBy($orderExpr, $direction);
        if ($sort === 'nom') {
            $qb->addOrderBy('u.prenom', $direction);
        }

        return array_values($qb->getQuery()->getArrayResult());
    }

    /**
     * @deprecated use findForAdminListing()
     * @return list<array<string, mixed>>
     */
    public function findAllForAdminListing(): array
    {
        return $this->findForAdminListing();
    }

    public function login(string $email, string $password): ?Utilisateur
    {
        $utilisateur = $this->findByEmail($email);
        
        if (!$utilisateur) {
            return null;
        }
        
        // Login is allowed only for active/actif statuses.
        $statut = strtolower(trim((string) $utilisateur->getStatut()));
        if (!\in_array($statut, ['active', 'actif'], true)) {
            return null;
        }
        
        // Comparaison directe des mots de passe en clair
        if ($password === $utilisateur->getPassword()) {
            return $utilisateur;
        }
        
        return null;
    }

    public function create(Utilisateur $utilisateur): void
    {
        // Définir la date d'inscription
        $utilisateur->setDateInscription(new \DateTime());
        
        $this->getEntityManager()->persist($utilisateur);
        $this->getEntityManager()->flush();
    }
}
