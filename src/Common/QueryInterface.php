<?php
declare(strict_types=1);

namespace vavo\Common;

/**
 * @template TKey
 * @template TValue
 */
interface QueryInterface
{
    public function count(Queryable $queryable) : int;

    public function fetch(Queryable $queryable, HydrationMode $hydrationMode) : ResultSetInterface | array;

    public function fetchOne(Queryable $queryable) : mixed;
}
