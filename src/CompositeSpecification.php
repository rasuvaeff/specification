<?php

declare(strict_types=1);

namespace Rasuvaeff\Specification;

/**
 * @implements Specification<mixed>
 * @api
 */
final readonly class CompositeSpecification implements Specification
{
    /**
     * @param Specification[] $specifications
     */
    public function __construct(
        private array $specifications = [],
    ) {}

    /**
     * @template TVisitorReturn
     * @param SpecificationVisitor<TVisitorReturn> $visitor
     * @return TVisitorReturn
     */
    #[\Override]
    public function accept(SpecificationVisitor $visitor)
    {
        return $visitor->visitComposite($this);
    }

    public function withSpecification(Specification $specification): self
    {
        return new self([...$this->specifications, $specification]);
    }

    /**
     * @param array<string, string|int|float|bool|array<mixed>|null> $conditions
     */
    public function withOrCondition(array $conditions): self
    {
        return $this->withSpecification(specification: OrConditionSpecification::fromArray(conditions: $conditions));
    }

    public function withNot(Specification $specification): self
    {
        return $this->withSpecification(specification: new NotSpecification(specification: $specification));
    }

    /**
     * @param array<string, string|int> $columns Column => direction pairs
     */
    public function withOrderBy(array $columns): self
    {
        return $this->withSpecification(specification: new OrderBySpecification(columns: $columns));
    }

    public function withLimit(int $limit): self
    {
        return $this->withSpecification(specification: new LimitSpecification(limit: $limit));
    }

    public function withOffset(int $offset): self
    {
        return $this->withSpecification(specification: new OffsetSpecification(offset: $offset));
    }

    /**
     * @param string|array<mixed> $condition
     * @param array<string, mixed> $params
     */
    public function withRaw(string|array $condition, array $params = []): self
    {
        return $this->withSpecification(specification: new RawSpecification(condition: $condition, params: $params));
    }

    public static function create(): self
    {
        return new self();
    }

    public function withComparison(string $column, string|int|float|bool|array|\DateTimeInterface|null $value, string $operator = '='): self
    {
        return $this->withSpecification(specification: new ComparisonSpecification(column: $column, value: $value, operator: $operator));
    }

    /**
     * @return Specification[]
     */
    public function getSpecifications(): array
    {
        return $this->specifications;
    }
}
