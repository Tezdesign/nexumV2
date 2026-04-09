<?php

namespace App\Repository\UserHandling;

use App\Entity\UserHandling\Reclamation;
use App\Entity\UserHandling\Utilisateur;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\ORM\Query\Expr\Join;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Reclamation>
 */
class ReclamationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Reclamation::class);
    }

    /**
     * @return array{total: int, pending: int, in_progress: int, resolved: int}
     */
    public function getAdminReclamationStats(): array
    {
        $rows = $this->createQueryBuilder('r')
            ->select('r.statut AS s, COUNT(r.idRec) AS c')
            ->groupBy('r.statut')
            ->getQuery()
            ->getArrayResult();

        $pending = 0;
        $inProgress = 0;
        $resolved = 0;
        $resolvedLabels = ['resolved', 'traité', 'traite', 'fermé', 'ferme', 'closed', 'clôturé', 'cloture', 'resolu', 'résolu', 'rejected', 'refusé', 'refuse'];
        $progressLabels = ['in_progress', 'en_cours', 'encours', 'processing', 'en_traitement', 'en cours', 'in review', 'examen'];

        foreach ($rows as $row) {
            $c = (int) $row['c'];
            $label = strtolower(trim((string) ($row['s'] ?? '')));
            if ($label === '' || $label === '(none)') {
                $pending += $c;

                continue;
            }
            if (\in_array($label, $resolvedLabels, true)) {
                $resolved += $c;
            } elseif (\in_array($label, $progressLabels, true)) {
                $inProgress += $c;
            } else {
                $pending += $c;
            }
        }

        $total = (int) $this->createQueryBuilder('r')
            ->select('COUNT(r.idRec)')
            ->getQuery()
            ->getSingleScalarResult();

        return [
            'total' => $total,
            'pending' => $pending,
            'in_progress' => $inProgress,
            'resolved' => $resolved,
        ];
    }

    /**
     * @return array{labels: list<string>, values: list<int>}
     */
    public function getChartDataByStatut(): array
    {
        $rows = $this->createQueryBuilder('r')
            ->select('COALESCE(r.statut, \'(none)\') AS label, COUNT(r.idRec) AS c')
            ->groupBy('r.statut')
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
    public function getChartDataByCategorie(): array
    {
        $rows = $this->createQueryBuilder('r')
            ->select('r.categorie AS label, COUNT(r.idRec) AS c')
            ->groupBy('r.categorie')
            ->getQuery()
            ->getArrayResult();

        if ($rows === []) {
            return ['labels' => ['—'], 'values' => [0]];
        }

        foreach ($rows as &$row) {
            $l = $row['label'] ?? null;
            $row['label'] = ($l === null || trim((string) $l) === '') ? '(uncategorized)' : (string) $l;
        }
        unset($row);

        usort($rows, static fn (array $a, array $b): int => (int) $b['c'] <=> (int) $a['c']);

        return [
            'labels' => array_map(static fn (array $r) => (string) ($r['label'] ?? '?'), $rows),
            'values' => array_map(static fn (array $r) => (int) $r['c'], $rows),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function findForAdminListing(string $sort = 'idRec', string $direction = 'DESC'): array
    {
        $allowedSort = [
            'idRec' => 'r.idRec',
            'titre' => 'r.titre',
            'categorie' => 'r.categorie',
            'projet' => 'r.projet',
            'statut' => 'r.statut',
            'date' => 'r.date',
            'id_user' => 'r.id_user',
        ];
        $orderExpr = $allowedSort[$sort] ?? 'r.idRec';
        $direction = strtoupper($direction) === 'ASC' ? 'ASC' : 'DESC';

        return $this->createQueryBuilder('r')
            ->select([
                'r.idRec AS idRec',
                'r.titre AS titre',
                'r.categorie AS categorie',
                'r.projet AS projet',
                'r.statut AS statut',
                'r.date AS date',
                'r.id_user AS id_user',
                'u.prenom AS user_prenom',
                'u.nom AS user_nom',
                'u.email AS user_email',
            ])
            ->leftJoin(Utilisateur::class, 'u', Join::WITH, 'u.id = r.id_user')
            ->orderBy($orderExpr, $direction)
            ->addOrderBy('r.idRec', $direction)
            ->getQuery()
            ->getArrayResult();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function findForUserListing(int $userId, string $sort = 'idRec', string $direction = 'DESC'): array
    {
        $allowedSort = [
            'idRec' => 'r.idRec',
            'titre' => 'r.titre',
            'categorie' => 'r.categorie',
            'projet' => 'r.projet',
            'statut' => 'r.statut',
            'date' => 'r.date',
        ];
        $orderExpr = $allowedSort[$sort] ?? 'r.idRec';
        $direction = strtoupper($direction) === 'ASC' ? 'ASC' : 'DESC';

        return $this->createQueryBuilder('r')
            ->select([
                'r.idRec AS idRec',
                'r.titre AS titre',
                'r.categorie AS categorie',
                'r.projet AS projet',
                'r.statut AS statut',
                'r.date AS date',
                'r.id_user AS id_user',
            ])
            ->andWhere('r.id_user = :uid')
            ->setParameter('uid', $userId)
            ->orderBy($orderExpr, $direction)
            ->addOrderBy('r.idRec', $direction)
            ->getQuery()
            ->getArrayResult();
    }

    /**
     * @return array{statuts: array{labels: list<string>, values: list<int>}, categories: array{labels: list<string>, values: list<int>}}
     */
    public function getChartDataForUser(int $userId): array
    {
        $statutRows = $this->createQueryBuilder('r')
            ->select('COALESCE(r.statut, \'(none)\') AS label, COUNT(r.idRec) AS c')
            ->andWhere('r.id_user = :uid')
            ->setParameter('uid', $userId)
            ->groupBy('r.statut')
            ->getQuery()
            ->getArrayResult();

        $catRows = $this->createQueryBuilder('r')
            ->select('r.categorie AS label, COUNT(r.idRec) AS c')
            ->andWhere('r.id_user = :uid')
            ->setParameter('uid', $userId)
            ->groupBy('r.categorie')
            ->getQuery()
            ->getArrayResult();

        foreach ($catRows as &$row) {
            $l = $row['label'] ?? null;
            $row['label'] = ($l === null || trim((string) $l) === '') ? '(uncategorized)' : (string) $l;
        }
        unset($row);

        $normalize = static function (array $rows): array {
            if ($rows === []) {
                return ['labels' => ['—'], 'values' => [0]];
            }
            usort($rows, static fn (array $a, array $b): int => (int) $b['c'] <=> (int) $a['c']);

            return [
                'labels' => array_map(static fn (array $r) => (string) ($r['label'] ?? '?'), $rows),
                'values' => array_map(static fn (array $r) => (int) $r['c'], $rows),
            ];
        };

        return [
            'statuts' => $normalize($statutRows),
            'categories' => $normalize($catRows),
        ];
    }

    /**
     * @return array{total: int, pending: int, in_progress: int, resolved: int}
     */
    public function getUserReclamationStats(int $userId): array
    {
        $rows = $this->createQueryBuilder('r')
            ->select('r.statut AS s, COUNT(r.idRec) AS c')
            ->andWhere('r.id_user = :uid')
            ->setParameter('uid', $userId)
            ->groupBy('r.statut')
            ->getQuery()
            ->getArrayResult();

        $pending = 0;
        $inProgress = 0;
        $resolved = 0;
        $resolvedLabels = ['resolved', 'traité', 'traite', 'fermé', 'ferme', 'closed', 'clôturé', 'cloture', 'resolu', 'résolu', 'rejected', 'refusé', 'refuse'];
        $progressLabels = ['in_progress', 'en_cours', 'encours', 'processing', 'en_traitement', 'en cours', 'in review', 'examen'];

        foreach ($rows as $row) {
            $c = (int) $row['c'];
            $label = strtolower(trim((string) ($row['s'] ?? '')));
            if ($label === '' || $label === '(none)') {
                $pending += $c;

                continue;
            }
            if (\in_array($label, $resolvedLabels, true)) {
                $resolved += $c;
            } elseif (\in_array($label, $progressLabels, true)) {
                $inProgress += $c;
            } else {
                $pending += $c;
            }
        }

        $total = (int) $this->createQueryBuilder('r')
            ->select('COUNT(r.idRec)')
            ->andWhere('r.id_user = :uid')
            ->setParameter('uid', $userId)
            ->getQuery()
            ->getSingleScalarResult();

        return [
            'total' => $total,
            'pending' => $pending,
            'in_progress' => $inProgress,
            'resolved' => $resolved,
        ];
    }

    /**
     * @param list<int|string> $idRecs
     *
     * @return list<int>
     */
    public function filterIdsHavingFichier(array $idRecs): array
    {
        $idRecs = array_values(array_unique(array_filter(array_map(static fn ($v) => (int) $v, $idRecs))));
        if ($idRecs === []) {
            return [];
        }

        $col = $this->getEntityManager()->getConnection()->executeQuery(
            'SELECT id_rec FROM reclamation WHERE id_rec IN (?) AND fichier IS NOT NULL',
            [$idRecs],
            [ArrayParameterType::INTEGER]
        )->fetchFirstColumn();

        return array_map(static fn ($v) => (int) $v, $col);
    }
}
