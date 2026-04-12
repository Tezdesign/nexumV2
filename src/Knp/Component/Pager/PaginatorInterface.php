<?php

namespace Knp\Component\Pager;

use Knp\Component\Pager\Pagination\PaginationInterface;

interface PaginatorInterface
{
    public function paginate(mixed $target, int $page, int $limit = 10, array $options = []): PaginationInterface;
}
