<?php

declare(strict_types=1);

namespace Rasuvaeff\Specification;

use DateTimeInterface;
use LogicException;

/**
 * In-memory interpreter for a specification: evaluates it against a single row
 * (a column => value map the read model already holds). A rule written once as
 * a specification then holds in both places: this object walk answers whether
 * the predicate is TRUE of the row, and the SQL walk applies the same
 * construction to a query via {@see QueryBuildingVisitor} and
 * {@see QueryApplier}. The agreement of the two interpreters is guarded by a
 * property test against real SQL (#40).
 *
 * COMPARISON SEMANTICS, pinned by issue #40: SQL compares after type coercion
 * while `in_array(strict: true)` does not, so `'1'` against `1` agrees in
 * memory the way SQL would. Each side is coerced once: bool becomes 1/0, a
 * numeric string (`is_numeric()`) becomes int|float. Two numbers compare
 * numerically, two non-numeric strings byte-wise (SQL BINARY collation). An
 * ambiguous mix — a non-numeric string against a number, where the engines
 * disagree (`'b' > 0` is true on SQLite, false on MySQL) — is refused instead
 * of guessed.
 *
 * THREE-VALUED LOGIC: a row is reported only when the predicate is TRUE of
 * it, exactly as a WHERE clause keeps it. A NULL row value makes a scalar
 * comparison, a BETWEEN, or an IN/NOT IN over a NULL-less list UNKNOWN (not
 * false): `NOT (price < 0)` on a NULL price is UNKNOWN too — NOT of an
 * unknown is an unknown — so the row stays out, as it does in SQL. AND, OR
 * and NOT fold over TRUE / FALSE / UNKNOWN with SQL's absorption rules (TRUE
 * dominates OR, FALSE dominates AND); UNKNOWN projects to `false` at the
 * boundary, which is what a WHERE does.
 *
 * Refusals fail closed with a LogicException naming the construct: raw SQL
 * ({@see RawSpecification}), raw OR conditions ({@see OrConditionSpecification}),
 * the `like` family (`like`, `not like`, `ilike`, `not ilike`) and
 * DateTimeInterface values — SQL renders dates time-zone aware and it is not
 * possible to name a string form that every engine would read back the same.
 *
 * NULL values mirror the SQL the package actually builds — yiisoft/db's
 * condition builder, not the textbook: a NULL entry of an IN/NOT IN list is
 * rendered as `IS NULL` beside the equality arms (IN) and demands
 * `IS NOT NULL` (NOT IN); an empty NOT IN list adds no condition at all,
 * an empty IN list is `0=1`. `=` / `!=` with a NULL value are IS NULL /
 * IS NOT NULL, exactly as yiisoft/db renders them (#34). Every column a
 * specification touches must exist in the row: a missing key is refused, not
 * silently treated as NULL. Ordering, LIMIT and OFFSET do not filter rows
 * and are ignored, as in SQL where they shape the result set without
 * contributing to the WHERE clause.
 *
 * @implements SpecificationVisitor<bool>
 * @api
 */
final readonly class InMemorySpecification implements SpecificationVisitor
{
    private const array LIKE_OPERATORS = ['like', 'not like', 'ilike', 'not ilike'];

    /**
     * @param Specification<mixed> $specification
     * @param array<string, mixed> $row
     */
    public static function satisfies(Specification $specification, array $row): bool
    {
        return $specification->accept(new self(row: $row)) === true;
    }

    /**
     * @param array<string, mixed> $row
     */
    private function __construct(
        private array $row,
    ) {}

    #[\Override]
    public function visitComparison(ComparisonSpecification $specification): bool
    {
        return $this->triState($specification) ?? false;
    }

    #[\Override]
    public function visitComposite(CompositeSpecification $specification): bool
    {
        return $this->triState($specification) ?? false;
    }

    #[\Override]
    public function visitOr(OrSpecification $specification): bool
    {
        return $this->triState($specification) ?? false;
    }

    #[\Override]
    public function visitNot(NotSpecification $specification): bool
    {
        $innerSpecification = $specification->getSpecification();

        if (!$innerSpecification instanceof NotSpecification && $this->contributesNoCondition($innerSpecification)) {
            throw new LogicException(message: 'NOT specification cannot be empty');
        }

        // NOT NOT collapses inside triState, as the SQL visitor skips the
        // subquery for a double negation — and with it the emptiness check
        // the direct path performs.
        return $this->triState($specification) ?? false;
    }

    #[\Override]
    public function visitRaw(RawSpecification $specification): never
    {
        throw new LogicException(message: 'Raw SQL conditions cannot be evaluated in memory');
    }

    #[\Override]
    public function visitOrCondition(OrConditionSpecification $specification): never
    {
        throw new LogicException(message: 'Raw OR conditions cannot be evaluated in memory');
    }

    #[\Override]
    public function visitOrderBy(OrderBySpecification $specification): bool
    {
        return true;
    }

    #[\Override]
    public function visitLimit(LimitSpecification $specification): bool
    {
        return true;
    }

    #[\Override]
    public function visitOffset(OffsetSpecification $specification): bool
    {
        return true;
    }

    /**
     * The predicate as SQL's three-valued logic has it: TRUE, FALSE, or
     * UNKNOWN (null) — a row is kept only by TRUE. UNKNOWN arises exactly
     * where SQL's does: a NULL row value under a scalar comparison or a
     * BETWEEN, and a NULL bound of a BETWEEN.
     */
    private function triState(Specification $specification): ?bool
    {
        if ($specification instanceof ComparisonSpecification) {
            return $this->comparisonTriState($specification);
        }

        if ($specification instanceof CompositeSpecification) {
            // An empty composite adds no condition — vacuously TRUE.
            $verdict = true;

            foreach ($specification->getSpecifications() as $childSpecification) {
                $child = $this->triState($childSpecification);

                if ($child === false) {
                    return false;
                }

                if ($child === null) {
                    $verdict = null;
                }
            }

            return $verdict;
        }

        if ($specification instanceof OrSpecification) {
            // An empty OR leaves the row set unconstrained — TRUE.
            $verdict = false;
            $sawChild = false;

            foreach ($specification->getSpecifications() as $childSpecification) {
                $sawChild = true;
                $child = $this->triState($childSpecification);

                if ($child === true) {
                    return true;
                }

                if ($child === null) {
                    $verdict = null;
                }
            }

            return $sawChild ? $verdict : true;
        }

        if ($specification instanceof NotSpecification) {
            // NOT NOT collapses to the inner specification itself: the SQL
            // visitor skips the subquery for a double negation.
            $inner = $specification->getSpecification();

            if ($inner instanceof NotSpecification) {
                return $this->triState($inner->getSpecification());
            }

            $verdict = $this->triState($inner);

            return $verdict === null ? null : !$verdict;
        }

        if (
            $specification instanceof OrderBySpecification
            || $specification instanceof LimitSpecification
            || $specification instanceof OffsetSpecification
        ) {
            // Modifiers shape the result set; they are no condition. TRUE.
            return true;
        }

        if ($specification instanceof RawSpecification) {
            throw new LogicException(message: 'Raw SQL conditions cannot be evaluated in memory');
        }

        if ($specification instanceof OrConditionSpecification) {
            throw new LogicException(message: 'Raw OR conditions cannot be evaluated in memory');
        }

        // A Specification implementation the interpreter does not know: the
        // same fail-closed refusal the other unknowns get.
        throw new LogicException(message: sprintf('Specification "%s" cannot be evaluated in memory', $specification::class));
    }

    private function comparisonTriState(ComparisonSpecification $specification): ?bool
    {
        // ComparisonSpecification's constructor lowercases the operator.
        $operator = $specification->getOperator();

        if (in_array(needle: $operator, haystack: self::LIKE_OPERATORS, strict: true)) {
            throw new LogicException(message: sprintf('The LIKE operator "%s" cannot be evaluated in memory', $operator));
        }

        if ($operator === 'is') {
            $operator = '=';
        } elseif ($operator === 'is not') {
            $operator = '!=';
        }

        $column = $specification->getColumn();
        $value = $specification->getValue();

        if ($value === null) {
            // The only constructible operators with a NULL value are `=`/`!=`/
            // `is`/`is not`, already normalized to `=`/`!=` above; a null
            // operand renders as IS NULL / IS NOT NULL, exactly as yiisoft/db
            // does for the same condition (see SqliteIntegrationTest, #34).
            return $this->columnValue(column: $column) === null
                ? $operator === '='
                : $operator !== '=';
        }

        if ($value instanceof DateTimeInterface) {
            throw new LogicException(message: sprintf('Date value for column "%s" cannot be evaluated in memory', $column));
        }

        $rowValue = $this->columnValue(column: $column);

        switch ($operator) {
            case 'in':
            case 'not in':
                /** @var array<array-key, mixed> $value the specification admits nothing else for IN */
                return $this->inListVerdict(
                    operator: $operator,
                    column: $column,
                    rowValue: $rowValue,
                    values: $value,
                );

            case 'between':
            case 'not between':
                /** @var array{0: mixed, 1: mixed} $value a zero-based two-element list, per the constructor */
                [$from, $to] = $value;

                // NULL BETWEEN anything is UNKNOWN — and so is NOT of it;
                // a NULL bound makes the predicate UNKNOWN for every row.
                if ($rowValue === null || $from === null || $to === null) {
                    return null;
                }

                $inside = $this->betweenVerdict(column: $column, rowValue: $rowValue, from: $from, to: $to);

                return $operator === 'between' ? $inside : !$inside;
        }

        // SQL: a scalar comparison over a NULL row value is UNKNOWN — the
        // row is not kept, and NOT of it stays UNKNOWN. A NULL value of the
        // specification was already turned into a null check above; IN/NOT IN
        // left this path — their UNKNOWN (a NULL-less list over a NULL row)
        // is inListVerdict's to answer.
        if ($rowValue === null) {
            return null;
        }

        return $this->compareValues(
            operator: $operator,
            column: $column,
            rowValue: $rowValue,
            value: $value,
        );
    }

    private function columnValue(string $column): mixed
    {
        if (!array_key_exists(key: $column, array: $this->row)) {
            throw new LogicException(message: sprintf('Column "%s" is not present in the row', $column));
        }

        return $this->row[$column];
    }

    /**
     * The semantics of the SQL the package actually builds — yiisoft/db's
     * condition builder, verified against in-memory SQLite. With a NULL
     * entry in the list both operators are total: IN renders `= x OR
     * IS NULL`, NOT IN renders `<> x AND IS NOT NULL`; an empty IN list is
     * `0=1` (FALSE), an empty NOT IN list adds no condition (TRUE). A
     * NULL-less list over a NULL row value is what its rendering says —
     * `= x` / `<> x` over NULL — UNKNOWN, and NOT of it stays UNKNOWN.
     *
     * @param array<array-key, mixed> $values
     */
    private function inListVerdict(string $operator, string $column, mixed $rowValue, array $values): ?bool
    {
        $found = false;
        $namesNull = false;

        foreach ($values as $entry) {
            if ($entry === null) {
                $namesNull = true;

                continue;
            }

            if ($rowValue !== null && $this->compareValues(operator: '=', column: $column, rowValue: $rowValue, value: $entry)) {
                $found = true;
            }
        }

        if ($namesNull) {
            return $operator === 'in'
                ? $found || $rowValue === null
                : !$found && $rowValue !== null;
        }

        if ($values === []) {
            return $operator !== 'in';
        }

        if ($rowValue === null) {
            return null;
        }

        return $operator === 'in' ? $found : !$found;
    }

    private function betweenVerdict(string $column, mixed $rowValue, mixed $from, mixed $to): bool
    {
        $geFrom = $this->compareValues(operator: '>=', column: $column, rowValue: $rowValue, value: $from);
        $leTo = $this->compareValues(operator: '<=', column: $column, rowValue: $rowValue, value: $to);

        return $geFrom && $leTo;
    }

    /**
     * Coerces both sides once (bool => 1/0, numeric string => int|float) and
     * then compares numerically or byte-wise. A non-numeric string against a
     * number is refused: SQLite orders all text after all numbers (so
     * `'b' > 0` is true there) while MySQL coerces the string to a number
     * (false) — no single reading to guess.
     */
    private function compareValues(string $operator, string $column, mixed $rowValue, mixed $value): bool
    {
        $left = $this->coerce(column: $column, value: $rowValue);
        $right = $this->coerce(column: $column, value: $value);

        $leftIsString = is_string($left);
        $rightIsString = is_string($right);

        if ($leftIsString !== $rightIsString) {
            throw new LogicException(
                message: sprintf(
                    'Ambiguous comparison for column "%s": a non-numeric string against a number, SQL engines disagree on this coercion',
                    $column,
                ),
            );
        }

        // After the guard both sides carry the same kind; <=> compares
        // numerically when both are numbers and byte-wise when both are
        // non-numeric strings.
        return $this->verdictBySign(comparison: $left <=> $right, operator: $operator);
    }

    private function coerce(string $column, mixed $value): int|float|string
    {
        if ($value instanceof DateTimeInterface) {
            throw new LogicException(message: sprintf('Date value for column "%s" cannot be evaluated in memory', $column));
        }

        if (is_bool(value: $value)) {
            return $value ? 1 : 0;
        }

        if (is_int(value: $value) || is_float(value: $value)) {
            return $value;
        }

        if (is_string(value: $value)) {
            if (!is_numeric(value: $value)) {
                return $value;
            }

            // A numeric string narrows to int when it spells one ('20'), to
            // float otherwise ('20.5', '1e3').
            $unsigned = ltrim(string: $value, characters: '+-');

            return $unsigned !== '' && strspn(string: $unsigned, characters: '0123456789') === strlen(string: $unsigned) ? (int) $value : (float) $value;
        }

        throw new LogicException(
            message: sprintf('Value of type "%s" in column "%s" cannot be evaluated in memory', get_debug_type($value), $column),
        );
    }

    /**
     * The comparison sign the operator asks for. Equality is the zero sign.
     * The operator set is the constructor's whitelist; anything else is a
     * hand-built specification and fails closed rather than falling through.
     */
    private function verdictBySign(int $comparison, string $operator): bool
    {
        return match ($operator) {
            '=' => $comparison === 0,
            '!=', '<>' => $comparison !== 0,
            '>' => $comparison > 0,
            '>=' => $comparison >= 0,
            '<' => $comparison < 0,
            '<=' => $comparison <= 0,
            default => throw new LogicException(message: sprintf('The operator "%s" cannot be evaluated in memory', $operator)),
        };
    }

    /**
     * Whether the specification contributes nothing to the WHERE clause, the
     * shapes for which the SQL visitor throws "NOT specification cannot be
     * empty": comparisons, the LIKE family, raw leaves and OR conditions
     * contribute; empty groups and the order/limit/offset modifiers do not.
     */
    private function contributesNoCondition(Specification $specification): bool
    {
        if (
            $specification instanceof OrderBySpecification
            || $specification instanceof LimitSpecification
            || $specification instanceof OffsetSpecification
        ) {
            return true;
        }

        if (
            $specification instanceof CompositeSpecification
            || $specification instanceof OrSpecification
        ) {
            foreach ($specification->getSpecifications() as $childSpecification) {
                if (!$this->contributesNoCondition($childSpecification)) {
                    return false;
                }
            }

            return true;
        }

        return false;
    }
}
