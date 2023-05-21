<?php declare(strict_types = 1);

namespace vavo\DoctrineORM;

use ArrayIterator;
use Closure;
use Doctrine;
use Doctrine\ORM\AbstractQuery;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Traversable;
use vavo\Common\HydrationMode;
use vavo\Common\Queryable;
use vavo\Common\QueryInterface;
use Webmozart\Assert\Assert;


/**
 * @template TValue of object
 *
 * @template-implements QueryInterface<int|string,TValue>
 */
abstract class QueryObject implements QueryInterface
{

	/** @var Closure[] */
	public array $onPostFetch = [];

	/** @var Closure[] */
	protected array $filter = [];

	/** @var Closure[] */
	protected array $select = [];

	/** @var Closure[] */
	protected array $orderBy = [];

	/** @var  Closure[] */
	protected array $onQuery = [];

	protected ?string $indexBy = null;

	private ?string $value = null;

	private ?string $key = null;

	private ?Query $lastQuery = null;

	private ?ResultSet $lastResult = null;


	public function count(Queryable $queryable, ?ResultSet $resultSet = null, ?Paginator $paginatedQuery = null): int
	{
		if ($query = $this->doCreateCountQuery($queryable)) {
			return (int) $this->toQuery($query)->getSingleScalarResult();
		}

		if ($paginatedQuery !== null) {
			return $paginatedQuery->count();
		}

		$query = $this->getQuery($queryable)
			->setFirstResult(null)
			->setMaxResults(null);

		$paginatedQuery = new Paginator($query, $resultSet === null);

		return $paginatedQuery->count();
	}

	/**
     *  @param Queryable<int|string, TValue> $queryable
	 * This method is optional, and if you don't provide one, Doctrine will auto-generate it.
	 */
	protected function doCreateCountQuery(Queryable $queryable) : ?QueryBuilder
	{
        return null;
	}

	private function toQuery(QueryBuilder|AbstractQuery $query): Query
	{
		if ($query instanceof Doctrine\ORM\QueryBuilder) {
			$query = $query->getQuery();
		}

		return $query;
	}

	/**
     * @param Queryable<int|string, TValue> $queryable
	 * @internal
	 */
	protected function getQuery(Queryable $queryable): Query
	{
		$query = $this->toQuery($this->doCreateQuery($queryable));

		if ($this->lastQuery instanceof Query && $this->lastQuery->getDQL() === $query->getDQL()) {
			$query = $this->lastQuery;
		}

		if ($this->lastQuery !== $query) {
			$this->lastResult = new ResultSet($query, $this, $queryable);
		}

		return $this->lastQuery = $query;
	}

	/**
     *  @param queryable<int|string, TValue> $queryable
	 * Override this if needed
	 */
	protected function doCreateQuery(Queryable $queryable): QueryBuilder
	{
        Assert::isInstanceOf($queryable, QueryBuilderInterface::class);
		$qb = $queryable->createQueryBuilder('e', $this->indexBy);

		$this->applySpecifications($qb);

		return $qb;
	}

	protected function applySpecifications(QueryBuilder $qb): void
	{
		if ($this->indexBy !== null) {
			$exploded = explode('.', $this->indexBy);
			$qb->indexBy($exploded[0], $exploded[1]);
		}

		if ($this->isPairsQuery()) {
			$qb->select([$this->getColumn($this->key), $this->getColumn($this->value)]);
		}

		foreach ($this->select as $select) {
			$select($qb);
		}

		foreach ($this->filter as $filter) {
			$filter($qb);
		}

		foreach ($this->orderBy as $order) {
			$order($qb);
		}
	}

	private function isPairsQuery(): bool
	{
		return $this->key !== null && $this->value !== null;
	}

	private function getColumn(string $column): string
	{
		if (!str_contains($column, '.')) {
			$column = 'e.' . $column;
		}

		return $column;
	}

    /**
     *  @param Queryable<int|string, TValue> $queryable
     *
     * @return ($hydrationMode is HydrationMode::OBJECT ? ResultSet<TValue> : array<int, mixed>)
     */
	public function fetch(Queryable $queryable, HydrationMode $hydrationMode = HydrationMode::OBJECT):  ResultSet | array
	{
		$query = $this->getQuery($queryable)
			->setFirstResult(null)
			->setMaxResults(null);

		foreach ($this->onQuery as $onQuery) {
			$onQuery($query);
		}

		if ($this->isPairsQuery()) {
			return array_column($query->getArrayResult(), $this->value, $this->key);
		}

		if ($hydrationMode === HydrationMode::OBJECT) {
			return $this->lastResult;
		}

        $doctrineHydrationMode = match ($hydrationMode) {
            HydrationMode::OBJECT => AbstractQuery::HYDRATE_OBJECT,
            HydrationMode::ARRAY => AbstractQuery::HYDRATE_ARRAY,
        };

		return $query->execute(null, $doctrineHydrationMode);
	}

    /**
     *  @param Queryable<int|string, TValue> $queryable
     */
	public function fetchOne(Queryable $queryable): object
	{
		$query = $this->getQuery($queryable)
			->setFirstResult(null)
			->setMaxResults(1);

		foreach ($this->onQuery as $onQuery) {
			$onQuery($query);
		}

		// getResult has to be called to have consistent result for the postFetch
		// this is the only way to main the INDEX BY value
		$singleResult = $query->getResult();

		if (!$singleResult) {
			throw new Doctrine\ORM\NoResultException(); // simulate getSingleResult()
		}

		$this->postFetch($queryable, new ArrayIterator($singleResult));

		return array_shift($singleResult);
	}

	public function postFetch(Queryable $queryable, Traversable $iterator): void
	{
		foreach ($this->onPostFetch as $handler) {
			$handler($this, $queryable, $iterator);
		}
	}

	/**
	 * @internal For Debugging purposes only!
	 */
	public function getLastQuery(): ?Query
	{
		return $this->lastQuery;
	}

	public function asPairs(string $key, string $value): self
	{
		$this->key = $key;
		$this->value = $value;

		return $this;
	}

	public function addJoin(string $alias, string|callable $propertyOrCallback, string $joinType = Query\Expr\Join::INNER_JOIN): self
	{
		$this->select[] = function (QueryBuilder $qb) use ($alias, $propertyOrCallback, $joinType): void {
			if (!in_array($alias, $qb->getAllAliases(), true)) {
				if (is_callable($propertyOrCallback)) {
					$propertyOrCallback($qb);

				} elseif ($joinType === Query\Expr\Join::LEFT_JOIN) {
					$qb->leftJoin($propertyOrCallback, $alias);

				} else {
						$qb->join($propertyOrCallback, $alias);
				}
			}
		};

		return $this;
	}

	public function groupBy(string $column, ?string $alias = null): self
	{
		$column = $this->getColumn($column);

		$alias ??= $column;

		$this->filter[] = function (QueryBuilder $qb) use ($column, $alias): void {
			$qb
				->addGroupBy($column)
				->addSelect(sprintf('IDENTITY(%s) AS %s', $column, $alias));
		};

		return $this;
	}

	public function orderBy(string $column, string $value): void
	{
		$column = $this->getColumn($column);

		$this->orderBy[] = function (QueryBuilder $qb) use ($column, $value): void {
			$qb->orderBy($column, $value);
		};
	}

	protected function indexBy(string $field): void
	{
		$this->indexBy = $this->getColumn($field);
	}

}
