<?php

declare(strict_types=1);

namespace Rasuvaeff\Specification;

use DateTimeInterface;
use InvalidArgumentException;

/**
 * @implements Specification<mixed>
 * @api
 */
final readonly class ComparisonSpecification implements Specification
{
    private const array VALID_OPERATORS = [
        '=', '!=', '<>', '>', '>=', '<', '<=',
        'like', 'not like', 'ilike', 'not ilike',
        'in', 'not in', 'between', 'not between',
        'is', 'is not',
    ];

    private string $operator;

    public function __construct(
        private string $column,
        private string|int|float|bool|array|DateTimeInterface|null $value,
        string $operator = '=',
    ) {
        $this->operator = strtolower(string: $operator);
        $this->validateOperator();
        $this->validateValue();
    }

    /**
     * @template TVisitorReturn
     * @param SpecificationVisitor<TVisitorReturn> $visitor
     * @return TVisitorReturn
     */
    #[\Override]
    public function accept(SpecificationVisitor $visitor)
    {
        return $visitor->visitComparison($this);
    }

    private function validateOperator(): void
    {
        if (!in_array(needle: $this->operator, haystack: self::VALID_OPERATORS, strict: true)) {
            throw new InvalidArgumentException(
                message: sprintf(
                    'Invalid operator "%s". Valid operators are: %s',
                    $this->operator,
                    implode(separator: ', ', array: self::VALID_OPERATORS),
                ),
            );
        }
    }

    private function validateValue(): void
    {
        if ($this->value === null) {
            $validForNull = ['=', '!=', '<>', 'is', 'is not'];
            if (!in_array(needle: $this->operator, haystack: $validForNull, strict: true)) {
                throw new InvalidArgumentException(
                    message: sprintf('Operator "%s" cannot be used with NULL value', $this->operator),
                );
            }

            return;
        }

        $arrayOperators = ['in', 'not in', 'between', 'not between'];
        if (in_array(needle: $this->operator, haystack: $arrayOperators, strict: true)) {
            if (!is_array(value: $this->value)) {
                throw new InvalidArgumentException(
                    message: sprintf('Operator "%s" requires array value', $this->operator),
                );
            }

            if (in_array(needle: $this->operator, haystack: ['between', 'not between'], strict: true) && count(value: $this->value) !== 2) {
                throw new InvalidArgumentException(
                    message: sprintf('Operator "%s" requires array with exactly two values', $this->operator),
                );
            }
        }

        $stringOperators = ['like', 'not like', 'ilike', 'not ilike'];
        if (in_array(needle: $this->operator, haystack: $stringOperators, strict: true) && !is_string(value: $this->value)) {
            throw new InvalidArgumentException(
                message: sprintf('Operator "%s" requires string value', $this->operator),
            );
        }
    }

    public static function equal(string $column, string|int|float|bool|array|DateTimeInterface|null $value): self
    {
        return new self(column: $column, value: $value);
    }

    public static function notEqual(string $column, string|int|float|bool|array|DateTimeInterface|null $value): self
    {
        return new self(column: $column, value: $value, operator: '!=');
    }

    public static function greaterThan(string $column, string|int|float|bool|DateTimeInterface $value): self
    {
        return new self(column: $column, value: $value, operator: '>');
    }

    public static function greaterThanOrEqual(string $column, string|int|float|bool|DateTimeInterface $value): self
    {
        return new self(column: $column, value: $value, operator: '>=');
    }

    public static function lessThan(string $column, string|int|float|bool|DateTimeInterface $value): self
    {
        return new self(column: $column, value: $value, operator: '<');
    }

    public static function lessThanOrEqual(string $column, string|int|float|bool|DateTimeInterface $value): self
    {
        return new self(column: $column, value: $value, operator: '<=');
    }

    public static function like(string $column, string $pattern): self
    {
        return new self(column: $column, value: $pattern, operator: 'like');
    }

    public static function notLike(string $column, string $pattern): self
    {
        return new self(column: $column, value: $pattern, operator: 'not like');
    }

    public static function ilike(string $column, string $pattern): self
    {
        return new self(column: $column, value: $pattern, operator: 'ilike');
    }

    public static function notIlike(string $column, string $pattern): self
    {
        return new self(column: $column, value: $pattern, operator: 'not ilike');
    }

    public static function startsWith(string $column, string $prefix): self
    {
        return new self(column: $column, value: $prefix . '%', operator: 'like');
    }

    public static function endsWith(string $column, string $suffix): self
    {
        return new self(column: $column, value: '%' . $suffix, operator: 'like');
    }

    public static function contains(string $column, string $substring): self
    {
        return new self(column: $column, value: '%' . $substring . '%', operator: 'like');
    }

    public static function in(string $column, array $values): self
    {
        return new self(column: $column, value: $values, operator: 'in');
    }

    public static function notIn(string $column, array $values): self
    {
        return new self(column: $column, value: $values, operator: 'not in');
    }

    public static function between(string $column, string|int|float|bool|DateTimeInterface $from, string|int|float|bool|DateTimeInterface $to): self
    {
        return new self(column: $column, value: [$from, $to], operator: 'between');
    }

    public static function notBetween(string $column, string|int|float|bool|DateTimeInterface $from, string|int|float|bool|DateTimeInterface $to): self
    {
        return new self(column: $column, value: [$from, $to], operator: 'not between');
    }

    public static function isNull(string $column): self
    {
        return new self(column: $column, value: null, operator: 'is');
    }

    public static function isNotNull(string $column): self
    {
        return new self(column: $column, value: null, operator: 'is not');
    }

    public function getColumn(): string
    {
        return $this->column;
    }

    public function getValue(): string|int|float|bool|array|DateTimeInterface|null
    {
        return $this->value;
    }

    public function getOperator(): string
    {
        return $this->operator;
    }
}
