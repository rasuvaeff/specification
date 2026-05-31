<?php

declare(strict_types=1);

namespace Rasuvaeff\Specification;

/**
 * @implements Specification<mixed>
 * @api
 */
final readonly class OffsetSpecification implements Specification
{
    public function __construct(
        private int $offset,
    ) {
        if ($offset < 0) {
            throw new \InvalidArgumentException('Offset must be non-negative.');
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
        return $visitor->visitOffset($this);
    }

    public function getOffset(): int
    {
        return $this->offset;
    }
}
