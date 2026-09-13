<?php

declare(strict_types=1);

namespace Rasuvaeff\Specification;

use Yiisoft\Db\Constant\ColumnType;
use Yiisoft\Db\Expression\ExpressionInterface;
use Yiisoft\Db\Expression\Value\DateTimeValue;
use Yiisoft\Db\Query\QueryInterface;
use Yiisoft\Db\QueryBuilder\Condition\LikeMode;

/**
 * @implements SpecificationVisitor<void>
 * @api
 */
final readonly class QueryBuildingVisitor implements SpecificationVisitor
{
    private const array LIKE_OPERATORS = ['like', 'not like', 'ilike', 'not ilike'];

    public function __construct(
        private QueryInterface $query,
    ) {}

    #[\Override]
    public function visitComparison(ComparisonSpecification $specification): void
    {
        $operator = mb_strtolower(string: $specification->getOperator());

        switch ($operator) {
            case 'between':
            case 'not between':
                $value = $specification->getValue();
                if (!is_array(value: $value) || !array_is_list(array: $value) || count(value: $value) !== 2) {
                    throw new \InvalidArgumentException(
                        message: sprintf('%s operator requires array with exactly two values', strtoupper(string: $operator)),
                    );
                }
                $this->query->andWhere([$operator, $specification->getColumn(), $this->normalizeValue(value: $value[0]), $this->normalizeValue(value: $value[1])]);
                break;

            case 'in':
            case 'not in':
                $value = $specification->getValue();
                if (!is_array(value: $value)) {
                    throw new \InvalidArgumentException(
                        message: sprintf('%s operator requires array value', strtoupper(string: $operator)),
                    );
                }
                $this->query->andWhere([$operator, $specification->getColumn(), array_map(callback: $this->normalizeValue(...), array: $value)]);
                break;

            case 'is':
            case 'is not':
                $this->query->andWhere([
                    $operator === 'is' ? '=' : '!=',
                    $specification->getColumn(),
                    $this->normalizeValue(value: $specification->getValue()),
                ]);
                break;

            case 'like':
            case 'not like':
            case 'ilike':
            case 'not ilike':
                /** @var string $value the specification admits nothing else for a LIKE */
                $value = $specification->getValue();
                $this->query->andWhere($this->likeCondition($operator, $specification->getColumn(), $value, $specification->getLikeMatch()));
                break;

            default:
                $this->query->andWhere([$operator, $specification->getColumn(), $this->normalizeValue(value: $specification->getValue())]);
        }
    }

    private function normalizeValue(mixed $value): mixed
    {
        if ($value instanceof \DateTimeInterface) {
            return new DateTimeValue(value: $value, type: ColumnType::DATETIMETZ, info: ['size' => 6]);
        }

        if (is_array(value: $value)) {
            foreach ($value as $key => $nestedValue) {
                $value[$key] = $this->normalizeValue(value: $nestedValue);
            }
        }

        return $value;
    }

    #[\Override]
    public function visitComposite(CompositeSpecification $specification): void
    {
        foreach ($specification->getSpecifications() as $childSpecification) {
            $childSpecification->accept($this);
        }
    }

    #[\Override]
    public function visitNot(NotSpecification $specification): void
    {
        $innerSpec = $specification->getSpecification();

        if ($innerSpec instanceof NotSpecification) {
            $innerSpec->getSpecification()->accept($this);

            return;
        }

        $subQuery = $this->createSubQuery();
        $visitor = new self($subQuery);
        $specification->getSpecification()->accept($visitor);

        $where = $subQuery->getWhere();
        if ($where === null) {
            throw new \InvalidArgumentException('NOT specification cannot be empty');
        }

        /** @var array<int<0, max>|non-empty-string, mixed> $subQueryParams */
        $subQueryParams = $subQuery->getParams();
        [$where, $params] = $this->remapConditionAndParams($where, $subQueryParams);
        $this->query->andWhere(['not', $where]);
        $this->query->addParams($params);
    }

    #[\Override]
    public function visitOr(OrSpecification $specification): void
    {
        /** @var list<string|array<mixed>|ExpressionInterface> $conditions */
        $conditions = [];

        foreach ($specification->getSpecifications() as $childSpecification) {
            $subQuery = $this->createSubQuery();
            $visitor = new self($subQuery);
            $childSpecification->accept($visitor);

            if ($subQuery->getOrderBy() !== []) {
                $this->query->addOrderBy($subQuery->getOrderBy());
            }
            if (($limit = $subQuery->getLimit()) !== null) {
                $this->query->limit($limit);
            }
            if (($offset = $subQuery->getOffset()) !== null) {
                $this->query->offset($offset);
            }

            $where = $subQuery->getWhere();
            if ($where === null) {
                continue;
            }

            /** @var array<int<0, max>|non-empty-string, mixed> $subQueryParams */
            $subQueryParams = $subQuery->getParams();
            [$where, $params] = $this->remapConditionAndParams($where, $subQueryParams);
            $conditions[] = $where;
            $this->query->addParams($params);
        }

        if ($conditions === []) {
            return;
        }

        if (count(value: $conditions) === 1) {
            $this->query->andWhere($conditions[0]);

            return;
        }

        $this->query->andWhere(['or', ...$conditions]);
    }

    #[\Override]
    public function visitOrCondition(OrConditionSpecification $specification): void
    {
        $conditions = $specification->getConditions();
        if ($conditions !== []) {
            $this->query->andWhere(['or', ...array_map($this->normalizeOrCondition(...), $conditions)]);
        }
    }

    /**
     * A LIKE written as `[operator, column, pattern]` carries the caller's
     * wildcards in the pattern, so it is sent verbatim. `is` / `is not` are
     * mapped to `=` / `!=` exactly as visitComparison() does: yiisoft/db
     * renders `IS NULL` for a null operand, while a verbatim `IS 'value'` is a
     * syntax error on MySQL and PostgreSQL (#34).
     *
     * @param array<array-key, mixed> $condition
     * @return array<array-key, mixed>
     */
    private function normalizeOrCondition(array $condition): array
    {
        if (!array_is_list(array: $condition) || count(value: $condition) !== 3) {
            return $this->normalizeCondition(condition: $condition);
        }
        [$operator, $column, $value] = $condition;
        if (!is_string(value: $operator) || !is_string(value: $column)) {
            return $this->normalizeCondition(condition: $condition);
        }
        $operator = mb_strtolower(string: $operator);
        if ($operator === 'is' || $operator === 'is not') {
            return [$operator === 'is' ? '=' : '!=', $column, $this->normalizeValue(value: $value)];
        }
        if (!is_string(value: $value) || !in_array(needle: $operator, haystack: self::LIKE_OPERATORS, strict: true)) {
            return $this->normalizeCondition(condition: $condition);
        }

        return $this->likeCondition($operator, $column, $value, LikeMatch::Pattern);
    }

    /**
     * @param array<array-key, mixed> $condition
     * @return array<array-key, mixed>
     */
    private function normalizeCondition(array $condition): array
    {
        foreach ($condition as $key => $value) {
            $condition[$key] = $this->normalizeValue(value: $value);
        }

        return $condition;
    }

    /**
     * yiisoft/db reads `mode`, `escape` and `caseSensitive` from named operands
     * only and defaults to escaping the value and wrapping it in `%…%`. A
     * pre-wildcarded value would search for a literal `%`, and `ilike` has no
     * condition class of its own — it is `LIKE` with `caseSensitive: false`,
     * which PostgreSQL renders as `ILIKE` and MySQL as a plain `LIKE`.
     *
     * @param 'like'|'not like'|'ilike'|'not ilike' $operator
     * @return array<array-key, mixed>
     */
    private function likeCondition(string $operator, string $column, string $value, LikeMatch $likeMatch): array
    {
        $condition = [str_starts_with(haystack: $operator, needle: 'not ') ? 'not like' : 'like', $column, $value];
        if (str_contains(haystack: $operator, needle: 'ilike')) {
            $condition['caseSensitive'] = false;
        }

        return $condition + match ($likeMatch) {
            LikeMatch::Pattern => ['mode' => LikeMode::Custom, 'escape' => false],
            LikeMatch::StartsWith => ['mode' => LikeMode::StartsWith],
            LikeMatch::EndsWith => ['mode' => LikeMode::EndsWith],
            LikeMatch::Contains => ['mode' => LikeMode::Contains],
        };
    }

    /**
     * Two raw leaves under one AND may reuse a placeholder name; without the
     * remap `Query::addParams()` keeps the last value for both (#31).
     */
    #[\Override]
    public function visitRaw(RawSpecification $specification): void
    {
        /** @var array<int<0, max>|non-empty-string, mixed> $rawParams */
        $rawParams = $specification->getParams();
        [$condition, $params] = $this->remapConditionAndParams($specification->getCondition(), $rawParams);
        $this->query->andWhere($condition);
        $this->query->addParams($params);
    }

    /**
     * Positional (`?`) parameters keep their integer keys: they cannot collide
     * by name, and a `:0` key would no longer bind to the `?`.
     *
     * @param string|array<mixed>|ExpressionInterface $condition
     * @param array<int<0, max>|non-empty-string, mixed> $params
     * @return array{0: string|array<mixed>|ExpressionInterface, 1: array<int<0, max>|non-empty-string, mixed>}
     */
    private function remapConditionAndParams(string|array|ExpressionInterface $condition, array $params): array
    {
        $currentParams = $this->queryStringParams();
        /** @var array<int<0, max>|non-empty-string, mixed> $renamedParams */
        $renamedParams = [];
        /** @var array<string, string> $replacements */
        $replacements = [];

        foreach ($params as $placeholder => $paramValue) {
            if (is_int($placeholder)) {
                $renamedParams[$placeholder] = $paramValue;

                continue;
            }

            $normalizedPlaceholder = $this->normalizePlaceholder($placeholder);
            $uniquePlaceholder = $this->makeUniquePlaceholder($normalizedPlaceholder, $currentParams, $renamedParams);
            $renamedParams[$uniquePlaceholder] = $paramValue;

            if ($uniquePlaceholder !== $normalizedPlaceholder) {
                $replacements[$normalizedPlaceholder] = $uniquePlaceholder;
            }

            $currentParams[$uniquePlaceholder] = $paramValue;
        }

        if ($replacements !== []) {
            $condition = $this->replacePlaceholders($condition, $replacements);
        }

        return [$condition, $renamedParams];
    }

    /**
     * @return array<string, mixed>
     */
    private function queryStringParams(): array
    {
        $rawParams = $this->query->getParams();
        $result = [];
        foreach (array_keys($rawParams) as $key) {
            if (is_string($key)) {
                $result[$key] = $rawParams[$key];
            }
        }

        return $result;
    }

    /**
     * Whole tokens only: `strtr()` would rewrite the `:sort` prefix of
     * `:sort_max` as well (#30). The lookbehind leaves a `::type` cast alone.
     * One pass over the original string, so a rename cannot cascade into
     * another rename's result.
     *
     * @param array<string, string> $replacements
     */
    private function replacePlaceholderTokens(string $condition, array $replacements): string
    {
        return preg_replace_callback(
            '/(?<![:\w]):\w+/',
            static fn(array $match): string => $replacements[$match[0]] ?? $match[0],
            $condition,
        ) ?? $condition;
    }

    /**
     * @param non-empty-string $placeholder
     * @param array<string, mixed> $currentParams
     * @param array<int<0, max>|non-empty-string, mixed> $renamedParams
     * @return non-empty-string
     */
    private function makeUniquePlaceholder(string $placeholder, array $currentParams, array $renamedParams): string
    {
        $candidate = $placeholder;
        $suffix = 0;

        while (array_key_exists($candidate, $currentParams) || array_key_exists($candidate, $renamedParams)) {
            $candidate = sprintf('%s_%d', $placeholder, $suffix);
            $suffix++;
        }

        return $candidate;
    }

    /**
     * @param string|array<mixed>|ExpressionInterface $condition
     * @param array<string, string> $replacements
     * @return string|array<mixed>|ExpressionInterface
     */
    private function replacePlaceholders(string|array|ExpressionInterface $condition, array $replacements): string|array|ExpressionInterface
    {
        if ($replacements === []) {
            return $condition;
        }

        if (is_string($condition)) {
            return $this->replacePlaceholderTokens($condition, $replacements);
        }

        if ($condition instanceof ExpressionInterface) {
            return $condition;
        }

        $keys = array_keys($condition);
        $values = array_values($condition);
        $normalizedCondition = [];

        foreach ($keys as $i => $key) {
            $value = $values[$i];
            $normalizedCondition[$key] = is_string($value) || is_array($value) || $value instanceof ExpressionInterface
                ? $this->replacePlaceholders($value, $replacements)
                : $value;
        }

        return $normalizedCondition;
    }

    /**
     * @return non-empty-string
     */
    private function normalizePlaceholder(string $placeholder): string
    {
        return str_starts_with($placeholder, ':') ? $placeholder : ':' . $placeholder;
    }

    private function createSubQuery(): QueryInterface
    {
        $subQuery = clone $this->query;
        $subQuery->setWhere(null);
        $subQuery->params([]);
        $subQuery->orderBy([]);
        $subQuery->limit(null);
        $subQuery->offset(null);

        return $subQuery;
    }

    #[\Override]
    public function visitOrderBy(OrderBySpecification $specification): void
    {
        $this->query->addOrderBy($this->normalizeOrderBy(columns: $specification->getColumns()));
    }

    #[\Override]
    public function visitLimit(LimitSpecification $specification): void
    {
        $this->query->limit($specification->getLimit());
    }

    #[\Override]
    public function visitOffset(OffsetSpecification $specification): void
    {
        $this->query->offset($specification->getOffset());
    }

    /**
     * @param array<string, int|string> $columns
     * @return array<string, int>
     */
    private function normalizeOrderBy(array $columns): array
    {
        $normalized = [];
        foreach ($columns as $column => $direction) {
            if (is_int(value: $direction)) {
                if (!in_array(needle: $direction, haystack: [SORT_ASC, SORT_DESC], strict: true)) {
                    throw new \InvalidArgumentException(
                        message: sprintf('Invalid order direction "%s" for column "%s"', $direction, $column),
                    );
                }

                $normalized[$column] = $direction;

                continue;
            }

            $upperDirection = strtoupper(string: $direction);
            if ($upperDirection === 'ASC') {
                $normalized[$column] = SORT_ASC;

                continue;
            }

            if ($upperDirection === 'DESC') {
                $normalized[$column] = SORT_DESC;

                continue;
            }

            throw new \InvalidArgumentException(
                message: sprintf('Invalid order direction "%s" for column "%s"', $direction, $column),
            );
        }

        return $normalized;
    }
}
