<?php
declare(strict_types=1);

namespace vavo\Common;

/**
 * @template TKey
 * @template TValue
 */
interface Queryable
{
    /**
     * @return QueryInterface<TKey,TValue>
     */
    public function createQuery() : QueryInterface;
}
