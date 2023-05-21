<?php declare(strict_types = 1);

/**
 * This file is part of the Kdyby (http://www.kdyby.org)
 *
 * Copyright (c) 2008 Filip Procházka (filip@prochazka.su)
 *
 * For the full copyright and license information, please view the file license.txt that was distributed with this source code.
 */

namespace vavo\DoctrineORM;

use ArrayIterator;
use Doctrine\ORM;
use Doctrine\ORM\AbstractQuery;
use Doctrine\ORM\NativeQuery;
use Doctrine\ORM\Query;
use Doctrine\ORM\Tools\Pagination\Paginator as ResultPaginator;
use vavo\Common\HydrationMode;
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

	private bool $fetchJoinCollection = true;

	private ?ArrayIterator $iterator = null;


	public function __construct(
        private readonly ORM\AbstractQuery $query,
        private readonly QueryObject      $queryObject,
        private readonly Queryable         $repository
    ) {
		if ($this->query instanceof NativeQuery) {
			$this->fetchJoinCollection = false;
		}
	}

	public function isEmpty(): bool
	{
		$count = $this->getTotalCount();
		$offset = $this->query instanceof ORM\Query ? $this->query->getFirstResult() : 0;

		return $count <= $offset;
	}


	public function getTotalCount(): int
	{
		if ($this->totalCount !== null) {
			return $this->totalCount;
		}

		$paginatedQuery = $this->createPaginatedQuery($this->query);

		$totalCount = $this->queryObject !== null && $this->repository !== null ? $this->queryObject->count($this->repository, $this, $paginatedQuery) : $paginatedQuery->count();

		return $this->totalCount = $totalCount;
	}


    /**
     * @return ArrayIterator<TValue>
     */
	public function getIterator(HydrationMode $hydrationMode = HydrationMode::OBJECT): ArrayIterator
	{
		if ($this->iterator !== null) {
			return $this->iterator;
		}

        $doctrineHydrationMode = match ($hydrationMode) {
            HydrationMode::OBJECT => AbstractQuery::HYDRATE_OBJECT,
            HydrationMode::ARRAY => AbstractQuery::HYDRATE_ARRAY,
        };

		$this->query->setHydrationMode($doctrineHydrationMode);

        if (
            $this->fetchJoinCollection &&
            $this->query instanceof ORM\Query &&
            ($this->query->getMaxResults() > 0 || $this->query->getFirstResult() > 0)
        ) {
            $iterator = $this->createPaginatedQuery($this->query)->getIterator();
        } else {
            $iterator = new ArrayIterator($this->query->getResult());
        }

		if ($this->queryObject !== null && $this->repository !== null) {
			$this->queryObject->postFetch($this->repository, $iterator);
		}

		return $this->iterator = $iterator;
	}


    /**
     * @return self<TValue>
     */
    public function applyPaging(?int $offset, ?int $limit): self
    {
        if ($this->query instanceof ORM\Query && ($this->query->getFirstResult() !== $offset || $this->query->getMaxResults() !== $limit)) {
            $this->query->setFirstResult($offset);
            $this->query->setMaxResults($limit);
            $this->iterator = null;
        }

        return $this;
    }

	public function toArray(): array
	{
		return iterator_to_array(clone $this->getIterator());
	}

	public function count(): int
	{
		return $this->getIterator()->count();
	}

	private function createPaginatedQuery(AbstractQuery $query): ORM\Tools\Pagination\Paginator
	{
        Assert::isInstanceOf($query, Query::class, sprintf('QueryObject pagination only works with %s', Query::class));

		return new ResultPaginator($query, $this->fetchJoinCollection);
	}
}
