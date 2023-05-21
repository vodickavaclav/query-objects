<?php
declare(strict_types=1);

namespace vavo\DoctrineORM;

use Doctrine\ORM\QueryBuilder;
use vavo\Common\Queryable;

interface QueryBuilderInterface extends Queryable
{
    public function createQueryBuilder(string $alias, ?string $indexBy = null) : QueryBuilder;
}
