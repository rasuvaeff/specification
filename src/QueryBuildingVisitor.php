<?php

declare(strict_types=1);

namespace Rasuvaeff\Specification;

use Yiisoft\Db\Expression\ExpressionInterface;
use Yiisoft\Db\Query\QueryInterface;

/**
 * @implements SpecificationVisitor<void>
 * @api
 */
final readonly class QueryBuildingVisitor implements SpecificationVisitor
{
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
                if (!is_array(value: $value) || count(value: $value) !== 2) {
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
                    $specification->getValue(),
                ]);
                break;

            default:
                $this->query->andWhere([$operator, $specification->getColumn(), $this->normalizeValue(value: $specification->getValue())]);
        }
    }

    private function normalizeValue(mixed $value): mixed
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
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
        /** @var array<int<0, max>|non-empty-string, mixed> $params */
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

            $where = $subQuery->getWhere();
            if ($where === null) {
                continue;
            }

            /** @var array<int<0, max>|non-empty-string, mixed> $subQueryParams */
            $subQueryParams = $subQuery->getParams();
            [$where, $params] = $this->remapConditionAndParams($where, $subQueryParams);
            $conditions[] = $where;
            /** @var array<int<0, max>|non-empty-string, mixed> $params */
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
            $this->query->andWhere(['or', ...$conditions]);
        }
    }

    #[\Override]
    public function visitRaw(RawSpecification $specification): void
    {
        $condition = $specification->getCondition();
        $this->query->andWhere($condition, $specification->getParams());
    }

    /**
     * @param string|array<mixed>|ExpressionInterface $condition
     * @param array<int<0, max>|non-empty-string, mixed> $params
     * @return array{0: string|array<mixed>|ExpressionInterface, 1: array<string, mixed>}
     */
    private function remapConditionAndParams(string|array|ExpressionInterface $condition, array $params): array
    {
        if ($params === []) {
            return [$condition, []];
        }

        $currentParams = $this->queryStringParams();
        /** @var array<string, mixed> $renamedParams */
        $renamedParams = [];
        /** @var array<string, string> $replacements */
        $replacements = [];

        $stringKeys = array_map(static fn(int|string $k): string => (string) $k, array_keys($params));
        $paramValues = array_values($params);

        foreach ($stringKeys as $i => $placeholder) {
            $normalizedPlaceholder = $this->normalizePlaceholder($placeholder);
            $uniquePlaceholder = $this->makeUniquePlaceholder($normalizedPlaceholder, $currentParams, $renamedParams);

            assert(array_key_exists($i, $paramValues));
            $renamedParams[$uniquePlaceholder] = $paramValues[$i];

            if ($uniquePlaceholder !== $normalizedPlaceholder) {
                $replacements[$normalizedPlaceholder] = $uniquePlaceholder;
            }

            $currentParams[$uniquePlaceholder] = $paramValues[$i];
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
     * @param array<string, mixed> $currentParams
     * @param array<string, mixed> $renamedParams
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
            return strtr($condition, $replacements);
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

    private function normalizePlaceholder(string $placeholder): string
    {
        return str_starts_with($placeholder, ':') ? $placeholder : ':' . $placeholder;
    }

    private function createSubQuery(): QueryInterface
    {
        $subQuery = clone $this->query;
        $subQuery->setWhere(null);
        $subQuery->params([]);

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
