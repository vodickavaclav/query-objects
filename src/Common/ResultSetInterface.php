<?php
declare(strict_types=1);

namespace vavo\Common;

use Countable;
use IteratorAggregate;

/**
 * @template TKey
 * @template TValue
 *
 * @template-extends IteratorAggregate<TKey, TValue>
 */
interface ResultSetInterface extends \IteratorAggregate, Countable
{
    /**
     * Return total number of elements meeting the criteria
     */
    public function getTotalCount() : int;

    /**
     * Return number of elements in the iterator
     */
    public function count() : int;

    public function isEmpty() : bool;

    public function getIterator() : \Iterator;

    public function applyPaging(?int $offset, ?int $limit): self;
}
