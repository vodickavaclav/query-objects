<?php
declare(strict_types=1);

namespace vavo\DoctrineODM;

use Doctrine\ODM\MongoDB\Query\Builder;
use vavo\Common\Queryable;

interface QueryBuilderInterface extends Queryable
{
    public function createQueryBuilder() : Builder;
}
