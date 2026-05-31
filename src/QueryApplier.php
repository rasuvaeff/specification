<?php

declare(strict_types=1);

namespace Rasuvaeff\Specification;

use Yiisoft\Db\Query\QueryInterface;

/**
 * @api
 */
final readonly class QueryApplier
{
    /**
     * @param Specification<mixed> $specification
     */
    public static function apply(Specification $specification, QueryInterface $query): void
    {
        $visitor = new QueryBuildingVisitor(query: $query);
        $specification->accept($visitor);
    }
}
