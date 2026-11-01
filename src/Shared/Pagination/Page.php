<?php

declare(strict_types=1);

namespace App\Shared\Pagination;

use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\Tools\Pagination\Paginator;

/**
 * Une page de résultats d'une requête Doctrine.
 *
 * @template T
 *
 * @implements \IteratorAggregate<int, T>
 */
final readonly class Page implements \IteratorAggregate, \Countable
{
    /**
     * @param list<T> $items
     */
    private function __construct(
        public array $items,
        public int $number,
        public int $perPage,
        public int $total,
    ) {
    }

    /**
     * @return self<mixed>
     */
    public static function fromQuery(QueryBuilder $query, int $number, int $perPage): self
    {
        $number = max(1, $number);
        $query->setFirstResult(($number - 1) * $perPage)->setMaxResults($perPage);
        $paginator = new Paginator($query);

        return new self(array_values(iterator_to_array($paginator)), $number, $perPage, \count($paginator));
    }

    /**
     * @return \ArrayIterator<int, T>
     */
    public function getIterator(): \ArrayIterator
    {
        return new \ArrayIterator($this->items);
    }

    public function count(): int
    {
        return \count($this->items);
    }

    public function pageCount(): int
    {
        return max(1, (int) ceil($this->total / $this->perPage));
    }

    public function hasPrevious(): bool
    {
        return $this->number > 1;
    }

    public function hasNext(): bool
    {
        return $this->number < $this->pageCount();
    }

    public function firstIndex(): int
    {
        return 0 === $this->total ? 0 : ($this->number - 1) * $this->perPage + 1;
    }

    public function lastIndex(): int
    {
        return min($this->total, $this->number * $this->perPage);
    }
}
