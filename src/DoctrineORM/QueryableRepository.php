<?php
declare(strict_types=1);

namespace vavo\DoctrineORM;

use Doctrine\Bundle\MongoDBBundle\Repository\ServiceDocumentRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Mapping\ClassMetadata;
use vavo\Common\HydrationMode;
use vavo\Common\Queryable;
use vavo\Common\QueryExecutorInterface;
use vavo\Common\QueryInterface;
use Webmozart\Assert\Assert;

/**
 * @template TValue of object
 *
 * @template-extends ServiceDocumentRepository<TValue>
 *
 * @template-implements QueryExecutorInterface<int|string, TValue>
 * @template-implements Queryable<int|string, TValue>
 */
abstract class QueryableRepository extends EntityRepository implements QueryBuilderInterface, QueryExecutorInterface
{
    /**
     * @param class-string<QueryInterface<int|string, TValue>> $queryClass
     */
    public function __construct(EntityManagerInterface $em, ClassMetadata $class, readonly string $queryClass)
    {
        parent::__construct($em, $class);
    }

    /**
     * @param QueryObject<TValue> $queryObject
     *
     * @return ($hydrationMode is HydrationMode::OBJECT ? ResultSet<TValue> : array<int, mixed>)
     */
    public function fetch(QueryInterface $queryObject, HydrationMode $hydrationMode = HydrationMode::OBJECT) : ResultSet | array
    {
        return $queryObject->fetch($this, $hydrationMode);
    }

    /**
     * @param QueryObject<TValue> $queryObject
     *
     * @return ($hydrationMode is HydrationMode::OBJECT ? TValue : array<int|string, mixed>) | null
     */
    public function fetchOne(QueryInterface $queryObject, HydrationMode $hydrationMode = HydrationMode::OBJECT) : mixed
    {
        return $queryObject->fetchOne($this);
    }

    /**
     * @param QueryObject<TValue> $queryObject
     */
    public function countQuery(QueryInterface $queryObject) : int
    {
        return $queryObject->count($this);
    }

    /**
     * @return QueryObject<TValue>
     */
    public function createQuery() : QueryObject
    {
        $queryObject = new $this->queryClass();
        Assert::isInstanceOf($queryObject, QueryObject::class);

        return $queryObject;
    }
}
