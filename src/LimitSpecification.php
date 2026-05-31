<?php

declare(strict_types=1);

namespace Rasuvaeff\Specification;

/**
 * @implements Specification<mixed>
 * @api
 */
final readonly class LimitSpecification implements Specification
{
    public function __construct(
        private int $limit,
    ) {
        if ($limit < 0) {
            throw new \InvalidArgumentException('Limit must be non-negative.');
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
        return $visitor->visitLimit($this);
    }

    public function getLimit(): int
    {
        return $this->limit;
    }
}
