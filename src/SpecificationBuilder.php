<?php

declare(strict_types=1);

namespace Rasuvaeff\Specification;

/**
 * @psalm-consistent-constructor
 * @api
 */
final class SpecificationBuilder
{
    private CompositeSpecification $specification;

    private function __construct(
        private bool $mutable = false,
    ) {
        $this->specification = CompositeSpecification::create();
    }

    public function where(string $column, string|int|float|bool|array|\DateTimeInterface|null $value, string $operator = '=', ?LikeMatch $likeMatch = null): self
    {
        $builder = $this->mutable ? $this : clone $this;
        $builder->specification = $builder->specification->withComparison(column: $column, value: $value, operator: $operator, likeMatch: $likeMatch);

        return $builder;
    }

    public function whereEqual(string $column, string|int|float|bool|array|\DateTimeInterface|null $value): self
    {
        return $this->where(column: $column, value: $value);
    }

    public function whereNotEqual(string $column, string|int|float|bool|array|\DateTimeInterface|null $value): self
    {
        return $this->where(column: $column, value: $value, operator: '!=');
    }

    public function whereGreaterThan(string $column, string|int|float|bool|\DateTimeInterface $value): self
    {
        return $this->where(column: $column, value: $value, operator: '>');
    }

    public function whereLessThan(string $column, string|int|float|bool|\DateTimeInterface $value): self
    {
        return $this->where(column: $column, value: $value, operator: '<');
    }

    public function whereIn(string $column, array $values): self
    {
        return $this->where(column: $column, value: $values, operator: 'in');
    }

    public function whereNotIn(string $column, array $values): self
    {
        return $this->where(column: $column, value: $values, operator: 'not in');
    }

    public function whereLike(string $column, string $pattern): self
    {
        return $this->where(column: $column, value: $pattern, operator: 'like');
    }

    public function whereNotLike(string $column, string $pattern): self
    {
        return $this->where(column: $column, value: $pattern, operator: 'not like');
    }

    public function whereBetween(string $column, string|int|float|bool|\DateTimeInterface $from, string|int|float|bool|\DateTimeInterface $to): self
    {
        return $this->where(column: $column, value: [$from, $to], operator: 'between');
    }

    public function whereNull(string $column): self
    {
        return $this->where(column: $column, value: null, operator: 'is');
    }

    public function whereNotNull(string $column): self
    {
        return $this->where(column: $column, value: null, operator: 'is not');
    }

    public function whereGreaterThanOrEqual(string $column, string|int|float|bool|\DateTimeInterface $value): self
    {
        return $this->where(column: $column, value: $value, operator: '>=');
    }

    public function whereLessThanOrEqual(string $column, string|int|float|bool|\DateTimeInterface $value): self
    {
        return $this->where(column: $column, value: $value, operator: '<=');
    }

    public function whereNotBetween(string $column, string|int|float|bool|\DateTimeInterface $from, string|int|float|bool|\DateTimeInterface $to): self
    {
        return $this->where(column: $column, value: [$from, $to], operator: 'not between');
    }

    public function whereIlike(string $column, string $pattern): self
    {
        return $this->where(column: $column, value: $pattern, operator: 'ilike');
    }

    public function whereNotIlike(string $column, string $pattern): self
    {
        return $this->where(column: $column, value: $pattern, operator: 'not ilike');
    }

    public function whereStartsWith(string $column, string $prefix): self
    {
        return $this->where(column: $column, value: $prefix, operator: 'like', likeMatch: LikeMatch::StartsWith);
    }

    public function whereEndsWith(string $column, string $suffix): self
    {
        return $this->where(column: $column, value: $suffix, operator: 'like', likeMatch: LikeMatch::EndsWith);
    }

    public function whereContains(string $column, string $substring): self
    {
        return $this->where(column: $column, value: $substring, operator: 'like', likeMatch: LikeMatch::Contains);
    }

    /**
     * @param callable(SpecificationBuilder): mixed $callback
     */
    public function orWhere(callable $callback): self
    {
        $builder = new self(mutable: true);
        $callbackResult = $callback($builder);
        if ($callbackResult instanceof self) {
            $builder = $callbackResult;
        }

        $newBuilder = clone $this;
        [$existingConditions, $existingModifiers] = $this->splitModifiers(specification: $this->specification);
        [$callbackConditions, $callbackModifiers] = $this->splitModifiers(specification: $builder->build());

        /** @var list<Specification> $orSpecifications */
        $orSpecifications = [];
        $callbackBranch = $callbackConditions === []
            ? null
            : new CompositeSpecification(specifications: $callbackConditions);
        if ($existingConditions !== []) {
            if (
                count($existingConditions) === 1
                && $existingConditions[0] instanceof OrSpecification
                && $callbackBranch instanceof CompositeSpecification
            ) {
                foreach ($existingConditions[0]->getSpecifications() as $existingSpecification) {
                    $orSpecifications[] = $existingSpecification;
                }
                $orSpecifications[] = $callbackBranch;
            } else {
                $orSpecifications[] = new CompositeSpecification(specifications: $existingConditions);
                if ($callbackBranch instanceof CompositeSpecification) {
                    $orSpecifications[] = $callbackBranch;
                }
            }
        } elseif ($callbackBranch instanceof CompositeSpecification) {
            $orSpecifications[] = $callbackBranch;
        }

        $newBuilder->specification = CompositeSpecification::create();
        if (count($orSpecifications) === 1) {
            foreach ($orSpecifications as $singleSpecification) {
                $newBuilder->specification = $newBuilder->specification->withSpecification(specification: $singleSpecification);
            }
        } elseif ($orSpecifications !== []) {
            $newBuilder->specification = $newBuilder->specification->withSpecification(
                specification: OrSpecification::create(...$orSpecifications),
            );
        }

        foreach ([...$existingModifiers, ...$callbackModifiers] as $modifier) {
            $newBuilder->specification = $newBuilder->specification->withSpecification(specification: $modifier);
        }

        return $newBuilder;
    }

    /**
     * @param callable(SpecificationBuilder): mixed $callback
     */
    public function notWhere(callable $callback): self
    {
        $builder = new self(mutable: true);
        $callbackResult = $callback($builder);
        if ($callbackResult instanceof self) {
            $builder = $callbackResult;
        }

        $newBuilder = clone $this;
        [$callbackConditions, $callbackModifiers] = $this->splitModifiers(specification: $builder->build());
        $newBuilder->specification = $newBuilder->specification->withNot(
            specification: new CompositeSpecification(specifications: $callbackConditions),
        );
        foreach ($callbackModifiers as $modifier) {
            $newBuilder->specification = $newBuilder->specification->withSpecification(specification: $modifier);
        }

        return $newBuilder;
    }

    /**
     * @param array<string, string|int> $columns
     */
    public function orderBy(array $columns): self
    {
        $builder = $this->mutable ? $this : clone $this;
        $builder->specification = $builder->specification->withOrderBy(columns: $columns);

        return $builder;
    }

    public function limit(int $limit): self
    {
        $builder = $this->mutable ? $this : clone $this;
        $builder->specification = $builder->specification->withLimit(limit: $limit);

        return $builder;
    }

    public function offset(int $offset): self
    {
        $builder = $this->mutable ? $this : clone $this;
        $builder->specification = $builder->specification->withOffset(offset: $offset);

        return $builder;
    }

    public function build(): CompositeSpecification
    {
        return $this->specification;
    }

    public static function create(): self
    {
        return new self();
    }

    /**
     * @return array{list<Specification>, list<Specification>}
     */
    private function splitModifiers(CompositeSpecification $specification): array
    {
        $conditions = [];
        $modifiers = [];

        foreach ($specification->getSpecifications() as $childSpecification) {
            if (
                $childSpecification instanceof OrderBySpecification
                || $childSpecification instanceof LimitSpecification
                || $childSpecification instanceof OffsetSpecification
            ) {
                $modifiers[] = $childSpecification;
            } else {
                $conditions[] = $childSpecification;
            }
        }

        return [$conditions, $modifiers];
    }
}
