<?php

namespace Knp\Component\Pager\Pagination;

use ArrayIterator;
use Countable;
use IteratorAggregate;
use Traversable;

final class Pagination implements PaginationInterface
{
    /**
     * @param array<int, mixed> $items
     */
    public function __construct(
        private readonly array $items,
        private readonly int $currentPageNumber,
        private readonly int $itemNumberPerPage,
        private readonly int $totalItemCount,
    ) {
    }

    public function getItems(): array
    {
        return $this->items;
    }

    public function getCurrentPageNumber(): int
    {
        return $this->currentPageNumber;
    }

    public function getItemNumberPerPage(): int
    {
        return $this->itemNumberPerPage;
    }

    public function getTotalItemCount(): int
    {
        return $this->totalItemCount;
    }

    public function getPageCount(): int
    {
        return max(1, (int) ceil($this->totalItemCount / max(1, $this->itemNumberPerPage)));
    }

    public function getFirstItemNumber(): int
    {
        if ($this->totalItemCount === 0) {
            return 0;
        }

        return (($this->currentPageNumber - 1) * $this->itemNumberPerPage) + 1;
    }

    public function getLastItemNumber(): int
    {
        return min($this->totalItemCount, $this->currentPageNumber * $this->itemNumberPerPage);
    }

    public function getPagesInRange(int $delta = 2): array
    {
        $pageCount = $this->getPageCount();
        $start = max(1, $this->currentPageNumber - max(0, $delta));
        $end = min($pageCount, $this->currentPageNumber + max(0, $delta));

        if ($start > $end) {
            return [];
        }

        return range($start, $end);
    }

    public function count(): int
    {
        return count($this->items);
    }

    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->items);
    }
}
