<?php

declare(strict_types=1);

namespace Rasuvaeff\Specification;

use InvalidArgumentException;

/**
 * @implements Specification<mixed>
 * @api
 */
final readonly class OrderBySpecification implements Specification
{
    /**
     * @param array<string, string|int> $columns Column => direction pairs
     */
    public function __construct(
        private array $columns,
    ) {
        if ($columns === []) {
            throw new InvalidArgumentException('Order by specification requires at least one column');
        }
    }

    /**
     * @template TVisitorReturn
     * @param SpecificationVisitor<TVisitorReturn> $visitor
     * @return TVisitorReturn
     */
    #[\Override]
    public function accept(SpecificationVisitor $visitor)
    {
        return $visitor->visitOrderBy($this);
    }

    public function getFirstColumn(): string
    {
        return array_keys($this->columns)[0];
    }

    public function getFirstDirection(): string|int
    {
        return array_values($this->columns)[0];
    }

    /**
     * @return array<string, string|int>
     */
    public function getColumns(): array
    {
        return $this->columns;
    }
}
