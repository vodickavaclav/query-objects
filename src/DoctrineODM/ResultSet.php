<?php
declare(strict_types=1);

namespace vavo\DoctrineODM;

use Doctrine\ODM\MongoDB\Iterator\Iterator;
use Doctrine\ODM\MongoDB\Query\Builder;
use vavo\Common\Queryable;
use vavo\Common\ResultSetInterface;
use Webmozart\Assert\Assert;

/**
 * @template TValue of object
 *
 * @template-implements ResultSetInterface<int|string,TValue>
 */
class ResultSet implements ResultSetInterface
{
    private ?int $totalCount = null;

    /** @var Iterator<TValue>|null */
    private ?Iterator $iterator = null;

    private ?int $limit = null;
    private ?int $offset = null;

    /**
     *  @param Queryable<int|string, TValue> $repository
     * @param QueryObject<TValue> $queryObject
     */
    public function __construct(
        private readonly Builder $builder,
        private readonly QueryObject $queryObject,
        private readonly Queryable $repository,
    ) {
    }

    public function isEmpty() : bool
    {
        return $this->getTotalCount() <= 0;
    }

    public function getTotalCount() : int
    {
        if ($this->totalCount !== null) {
            return $this->totalCount;
        }

        $totalCount = $this->queryObject->count($this->repository);

        return $this->totalCount = $totalCount;
    }

    /**
     * @return Iterator<TValue>
     */
    public function getIterator() : Iterator
    {
        if ($this->iterator !== null) {
            return $this->iterator;
        }

        $iterator = $this->builder->find()->getQuery()->getIterator();

        return $this->iterator = $iterator;
    }

    /**
     * @return self<TValue>
     */
    public function applyPaging(?int $offset, ?int $limit) : self
    {
        if ($offset !== null) {
            $this->offset = $offset;
            $this->builder->skip($offset);
        }

        if ($limit !== null) {
            $this->limit = $limit;
            $this->builder->limit($limit);
        }

        $this->iterator = null;

        return $this;
    }

    /**
     * Returns the number of results after applying the paging.
     */
    public function count() : int
    {
        $count = $this->builder->count()->getQuery()->execute();

        Assert::integer($count);
        Assert::range($count, 0, $this->limit ?? PHP_INT_MAX);

        return $count;
    }

    public function getLimit() : ?int
    {
        return $this->limit;
    }

    public function getOffset() : ?int
    {
        return $this->offset;
    }

    /**
     * @return TValue|null
     */
    public function first() : ?object
    {
        $paginatedResultArray = $this->applyPaging(0, 1)->getIterator()->toArray();
        $firstItemOfPaginatedArray = \reset($paginatedResultArray);
        if (empty($firstItemOfPaginatedArray)) {
            return null;
        }

        return $firstItemOfPaginatedArray;
    }

    /**
     * @return TValue|null
     */
    public function last() : ?object
    {
        if ($this->isEmpty()) {
            return null;
        }
        $paginatedResultArray = $this->applyPaging($this->getTotalCount() - 1, 1)->getIterator()->toArray();
        $firstItemOfPaginatedArray = \reset($paginatedResultArray);
        if (empty($firstItemOfPaginatedArray)) {
            return null;
        }

        return $firstItemOfPaginatedArray;
    }
}
