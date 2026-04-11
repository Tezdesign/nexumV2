<?php

namespace Knp\Component\Pager\Pagination;

use Countable;
use IteratorAggregate;

interface PaginationInterface extends Countable, IteratorAggregate
{
    public function getItems(): array;

    public function getCurrentPageNumber(): int;

    public function getItemNumberPerPage(): int;

    public function getTotalItemCount(): int;

    public function getPageCount(): int;

    public function getFirstItemNumber(): int;

    public function getLastItemNumber(): int;

    public function getPagesInRange(int $delta = 2): array;
}
