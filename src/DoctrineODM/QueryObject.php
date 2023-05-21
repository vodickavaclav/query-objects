<?php
declare(strict_types=1);

namespace vavo\DoctrineODM;

use Doctrine;
use Doctrine\ODM\MongoDB\Query\Builder;
use Doctrine\ODM\MongoDB\Query\Expr;
use Doctrine\ODM\MongoDB\Query\Query;
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
    /** @var \Closure[] */
    protected array $filter = [];

    /** @var \Closure[] */
    protected array $select = [];

    /** @var \Closure[] */
    protected array $sort = [];

    private ?Query $lastQuery = null;

    /** @var ResultSet<TValue>|null */
    private ?ResultSet $lastResult = null;


    public function count(Queryable $queryable) : int
    {
        $count = 0;

        if ($builder = $this->doCreateCountQuery($queryable)) {
            $count = $builder->count()->getQuery()->execute();
        }

        if (!$count) {
            $count = $this->doCreateQuery($queryable)->count()->getQuery()->execute();
        }

        Assert::integer($count);

        return $count;
    }

    /**
     * @param self<TValue> ...$queryObjects
     *
     * @return self<TValue>
     */
    public function addOr(self ...$queryObjects) : self
    {
        return $this->addFilter(function (Builder $builder) use ($queryObjects) {
            $builder->addOr(
                ...\array_map(
                    function (self $queryObject) use ($builder) {
                        $expr = $builder->expr();
                        $queryObject->applySpecifications($expr);

                        return $expr;
                    },
                    $queryObjects,
                ),
            );
        });
    }

    /**
     *  @param Queryable<int|string, TValue> $queryable
     * This method is optional, and if you don't provide one, Doctrine will auto-generate it
     */
    protected function doCreateCountQuery(Queryable $queryable) : ?Builder
    {
        return null;
    }

    private function toQuery(Builder | Query $query) : Query
    {
        if ($query instanceof Builder) {
            $query = $query->getQuery();
        }

        return $query;
    }

    /**
     *  @param Queryable<int|string, TValue> $queryable
     *
     *  @internal
     */
    protected function getQuery(Queryable $queryable, HydrationMode $hydrationMode) : Query
    {
        $queryBuilder = $this->doCreateQuery($queryable);
        $query = $this->toQuery($queryBuilder);

        $query->setHydrate($hydrationMode === HydrationMode::OBJECT);

        if ($this->lastQuery !== null && $this->lastQuery->getQuery() === $query->getQuery()) {
            $query = $this->lastQuery;
        }

        if ($this->lastQuery !== $query) {
            $this->lastResult = new ResultSet($queryBuilder, $this, $queryable);
        }

        return $this->lastQuery = $query;
    }

    /**
     *  @param queryable<int|string, TValue> $queryable
     * Override this if needed
     */
    public function doCreateQuery(Queryable $queryable) : Builder
    {
        $queryBuilder = $queryable->createQueryBuilder();
        Assert::isInstanceOf($queryBuilder, Builder::class);
        $this->applySpecifications($queryBuilder);

        return $queryBuilder;
    }

    protected function applySpecifications(Builder | Expr $builder) : void
    {
        foreach ($this->select as $select) {
            $select($builder);
        }

        foreach ($this->filter as $filter) {
            $filter($builder);
        }

        foreach ($this->sort as $order) {
            $order($builder);
        }
    }

    /**
     *  @param Queryable<int|string, TValue> $queryable
     *
     * @return ($hydrationMode is HydrationMode::OBJECT ? ResultSet<TValue> : array<int, mixed>)
     */
    public function fetch(Queryable $queryable, HydrationMode $hydrationMode = HydrationMode::OBJECT) : ResultSet | array
    {
        $query = $this->getQuery($queryable, $hydrationMode);

        if ($hydrationMode === HydrationMode::OBJECT) {
            Assert::notNull($this->lastResult);

            return $this->lastResult;
        }

        return $query->toArray();
    }

    /**
     *  @param Queryable<int|string, TValue> $queryable
     *
     * @return ($hydrationMode is HydrationMode::OBJECT ? TValue|null : array<int|string, mixed>|null)
     */
    public function fetchOne(Queryable $queryable, HydrationMode $hydrationMode = HydrationMode::OBJECT) : object | array | null
    {
        /** @var array<int|string, mixed>|TValue|null $object */
        $object = $this->getQuery($queryable, $hydrationMode)->getSingleResult();

        return $object;
    }

    /**
     * @return static<TValue>
     */
    public function addSort(string $fieldName, string $value) : self
    {
        $this->sort[] = function (Builder $qb) use ($fieldName, $value) : void {
            $qb->sort($fieldName, $value);
        };

        return $this;
    }

    /**
     * @return static<TValue>
     */
    public function addFilter(\Closure $filter) : self
    {
        $this->filter[] = $filter;

        return $this;
    }

    /**
     * @return static<TValue>
     */
    public function byId(string | ObjectId $id) : self
    {
        if (\is_string($id)) {
            $id = new ObjectId($id);
        }

        $this->filter[] = function (Builder $builder) use ($id) {
            $builder->field('_id')->equals($id);
        };

        return $this;
    }

    /**
     * @param array<ObjectId> $ids
     *
     * @return static<TValue>
     */
    public function byIds(array $ids) : self
    {
        $this->filter[] = function (Builder $builder) use ($ids) {
            $builder->field('_id')->in($ids);
        };

        return $this;
    }
}
