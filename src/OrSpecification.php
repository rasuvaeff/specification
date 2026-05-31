<?php

declare(strict_types=1);

namespace Rasuvaeff\Specification;

/**
 * @implements Specification<mixed>
 * @api
 */
final readonly class OrSpecification implements Specification
{
    /**
     * @param Specification[] $specifications
     */
    public function __construct(private array $specifications = []) {}

    /**
     * @template TVisitorReturn
     * @param SpecificationVisitor<TVisitorReturn> $visitor
     * @return TVisitorReturn
     */
    #[\Override]
    public function accept(SpecificationVisitor $visitor)
    {
        return $visitor->visitOr($this);
    }

    /**
     * @return Specification[]
     */
    public function getSpecifications(): array
    {
        return $this->specifications;
    }

    /**
     * Factory method for creating an OR condition
     */
    public static function create(Specification ...$specifications): self
    {
        return new self(specifications: $specifications);
    }
}
