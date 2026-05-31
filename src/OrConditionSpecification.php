<?php

declare(strict_types=1);

namespace Rasuvaeff\Specification;

/**
 * @implements Specification<mixed>
 * @api
 */
final readonly class OrConditionSpecification implements Specification
{
    private const array VALID_OPERATORS = [
        '=', '!=', '<>', '>', '>=', '<', '<=',
        'like', 'not like', 'ilike', 'not ilike',
        'in', 'not in', 'between', 'not between',
        'is', 'is not',
    ];

    /**
     * @param list<array<array-key, mixed>> $conditions
     */
    public function __construct(private array $conditions = []) {}

    /**
     * @template TVisitorReturn
     * @param SpecificationVisitor<TVisitorReturn> $visitor
     * @return TVisitorReturn
     */
    #[\Override]
    public function accept(SpecificationVisitor $visitor)
    {
        return $visitor->visitOrCondition($this);
    }

    /**
     * @param array<string, string|int|float|bool|array<mixed>|null> $conditions
     */
    public static function fromArray(array $conditions): self
    {
        $orConditions = [];

        foreach ($conditions as $column => $value) {
            $operator = is_array(value: $value) && array_key_exists(key: 0, array: $value) && is_string(value: $value[0])
                ? strtolower(string: $value[0])
                : null;

            if (!is_array(value: $value) || $operator === null || !in_array(needle: $operator, haystack: self::VALID_OPERATORS, strict: true)) {
                // Not an operator array: keep the value as-is. A plain hash with a
                // list value (e.g. ['active', 'pending']) becomes a yiisoft IN condition.
                $orConditions[] = [$column => $value];

                continue;
            }

            // $value is a non-empty list whose first element is a recognized operator.
            switch ($operator) {
                case 'between':
                case 'not between':
                    if (count(value: $value) === 4 && $value[1] === $column) {
                        // Already canonical: ['between', column, from, to]
                        $orConditions[] = [$operator, $column, $value[2], $value[3]];
                    } elseif (count(value: $value) === 3) {
                        // Shorthand: ['between', from, to]
                        $orConditions[] = [$operator, $column, $value[1], $value[2]];
                    } else {
                        $orConditions[] = [$column => $value];
                    }
                    break;

                case 'in':
                case 'not in':
                    if (count(value: $value) === 3 && $value[1] === $column && is_array(value: $value[2])) {
                        // Already canonical: ['in', column, [values]]
                        $orConditions[] = [$operator, $column, $value[2]];
                    } elseif (count(value: $value) === 2 && is_array(value: $value[1])) {
                        // Shorthand: ['in', [values]]
                        $orConditions[] = [$operator, $column, $value[1]];
                    } else {
                        $orConditions[] = [$column => $value];
                    }
                    break;

                default:
                    if (count(value: $value) === 3 && $value[1] === $column) {
                        // Already canonical: [operator, column, value]
                        $orConditions[] = [$operator, $column, $value[2]];
                    } elseif (count(value: $value) === 2) {
                        // Shorthand: [operator, value]
                        $orConditions[] = [$operator, $column, $value[1]];
                    } else {
                        $orConditions[] = [$column => $value];
                    }
            }
        }

        return new self($orConditions);
    }

    /**
     * @return list<array<array-key, mixed>>
     */
    public function getConditions(): array
    {
        return $this->conditions;
    }
}
