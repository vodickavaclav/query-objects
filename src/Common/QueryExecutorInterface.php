<?php
declare(strict_types=1);

namespace vavo\Common;


/**
 * @template TKey
 * @template TValue
 */
interface QueryExecutorInterface
{
    public function fetch(QueryInterface $queryObject, HydrationMode $hydrationMode = HydrationMode::OBJECT) : mixed;

    public function fetchOne(QueryInterface $queryObject, HydrationMode $hydrationMode = HydrationMode::OBJECT) : mixed;
}
