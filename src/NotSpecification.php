<?php

declare(strict_types=1);

namespace Rasuvaeff\Specification;

/**
 * @implements Specification<mixed>
 * @api
 */
final readonly class NotSpecification implements Specification
{
    public function __construct(
        private Specification $specification,
    ) {}

    /**
     * @template TVisitorReturn
     * @param SpecificationVisitor<TVisitorReturn> $visitor
     * @return TVisitorReturn
     */
    #[\Override]
    public function accept(SpecificationVisitor $visitor)
    {
        return $visitor->visitNot($this);
    }

    public function getSpecification(): Specification
    {
        return $this->specification;
    }

    public static function create(Specification $specification): self
    {
        return new self(specification: $specification);
    }
}
