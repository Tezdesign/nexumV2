<?php

namespace Knp\Component\Pager;

use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\Tools\Pagination\Paginator as DoctrinePaginator;
use InvalidArgumentException;
use Knp\Component\Pager\Pagination\Pagination;
use Knp\Component\Pager\Pagination\PaginationInterface;
use Traversable;

final class Paginator implements PaginatorInterface
{
    public function paginate(mixed $target, int $page, int $limit = 10, array $options = []): PaginationInterface
    {
        $page = max(1, $page);
        $limit = max(1, $limit);

        if ($target instanceof QueryBuilder) {
            $target = $target->getQuery();
        }

        if ($target instanceof Query) {
            $query = clone $target;
            $query->setFirstResult(($page - 1) * $limit);
            $query->setMaxResults($limit);

            $doctrinePaginator = new DoctrinePaginator($query);
            $items = iterator_to_array($doctrinePaginator->getIterator(), false);

            return new Pagination($items, $page, $limit, count($doctrinePaginator));
        }

        if ($target instanceof Traversable) {
            $target = iterator_to_array($target, false);
        }

        if (is_array($target)) {
            $totalItemCount = count($target);
            $offset = ($page - 1) * $limit;
            $items = array_slice($target, $offset, $limit);

            return new Pagination($items, $page, $limit, $totalItemCount);
        }

        throw new InvalidArgumentException('Unsupported pagination target.');
    }
}
