<?php

declare(strict_types=1);

namespace Rasuvaeff\Specification\Tests;

use InvalidArgumentException;
use Rasuvaeff\Specification\ComparisonSpecification;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Data\DataProvider;
use Testo\Expect;
use Testo\Test;

#[Test]
#[Covers(ComparisonSpecification::class)]
final class ComparisonSpecificationTest
{
    public function constructorWithValidOperator(): void
    {
        $spec = new ComparisonSpecification(
            column: 'age',
            value: 25,
            operator: '>',
        );

        Assert::same($spec->getColumn(), 'age');
        Assert::same($spec->getValue(), 25);
        Assert::same($spec->getOperator(), '>');
    }

    public function constructorWithDefaultOperator(): void
    {
        $spec = new ComparisonSpecification(
            column: 'name',
            value: 'John',
        );

        Assert::same($spec->getOperator(), '=');
    }

    public function throwsExceptionForInvalidOperator(): void
    {
        Expect::exception(InvalidArgumentException::class)->withMessageContaining('Invalid operator "invalid_op"');

        new ComparisonSpecification(
            column: 'age',
            value: 25,
            operator: 'invalid_op',
        );
    }

    public function acceptsVisitor(): void
    {
        $spec = new ComparisonSpecification(column: 'age', value: 25);
        $visitor = new FakeVisitor(returnValue: 'result');

        $result = $spec->accept(visitor: $visitor);

        Assert::same($visitor->lastMethod, 'visitComparison');
        Assert::same($visitor->lastArg, $spec);
        Assert::same($result, 'result');
    }

    // ---------- Null values ----------

    /** @return list<array{string}> */
    public static function nullAllowedOperatorsProvider(): array
    {
        return [
            ['='],
            ['!='],
            ['<>'],
            ['is'],
            ['is not'],
        ];
    }

    #[DataProvider('nullAllowedOperatorsProvider')]
    public function nullValueAllowed(string $operator): void
    {
        $spec = new ComparisonSpecification(column: 'col', value: null, operator: $operator);

        Assert::same($spec->getOperator(), $operator);
        Assert::null($spec->getValue());
    }

    /** @return list<array{string}> */
    public static function nullDisallowedOperatorsProvider(): array
    {
        return [
            ['>'],
            ['>='],
            ['<'],
            ['<='],
            ['like'],
            ['not like'],
            ['ilike'],
            ['not ilike'],
            ['in'],
            ['not in'],
            ['between'],
            ['not between'],
        ];
    }

    #[DataProvider('nullDisallowedOperatorsProvider')]
    public function nullValueDisallowed(string $operator): void
    {
        Expect::exception(InvalidArgumentException::class)->withMessageContaining('cannot be used with NULL value');

        new ComparisonSpecification(column: 'col', value: null, operator: $operator);
    }

    // ---------- Array operators ----------

    /** @return list<array{string, list<int>}> */
    public static function validArrayOperatorsProvider(): array
    {
        return [
            ['in', [1, 2, 3]],
            ['not in', [1, 2, 3]],
            ['between', [1, 10]],
            ['not between', [1, 10]],
        ];
    }

    #[DataProvider('validArrayOperatorsProvider')]
    public function validArrayOperators(string $operator, array $value): void
    {
        $spec = new ComparisonSpecification(column: 'col', value: $value, operator: $operator);

        Assert::same($spec->getOperator(), $operator);
        Assert::same($spec->getValue(), $value);
    }

    /** @return list<array{string, string|int|bool}> */
    public static function invalidArrayOperatorsProvider(): array
    {
        return [
            ['in', 123],
            ['in', 'string'],
            ['in', true],
            ['not in', 123],
            ['not in', 'string'],
            ['not in', true],
            ['between', 123],
            ['between', 'string'],
            ['between', true],
            ['not between', 123],
            ['not between', 'string'],
            ['not between', true],
        ];
    }

    #[DataProvider('invalidArrayOperatorsProvider')]
    public function arrayOperatorsRequireArray(string $operator, string|int|bool $value): void
    {
        Expect::exception(InvalidArgumentException::class)->withMessageContaining('requires array value');

        new ComparisonSpecification(column: 'col', value: $value, operator: $operator);
    }

    /** @return list<array{string, list<int>}> */
    public static function betweenElementCountProvider(): array
    {
        return [
            ['between', []],
            ['between', [1]],
            ['between', [1, 2, 3]],
            ['not between', []],
            ['not between', [1]],
            ['not between', [1, 2, 3]],
        ];
    }

    #[DataProvider('betweenElementCountProvider')]
    public function betweenRequiresExactlyTwoElements(string $operator, array $value): void
    {
        Expect::exception(InvalidArgumentException::class)->withMessageContaining('requires array with exactly two values');

        new ComparisonSpecification(column: 'col', value: $value, operator: $operator);
    }

    // ---------- String operators ----------

    /** @return list<array{string, string}> */
    public static function validStringOperatorsProvider(): array
    {
        return [
            ['like', 'test'],
            ['not like', 'test'],
            ['ilike', 'test'],
            ['not ilike', 'test'],
        ];
    }

    #[DataProvider('validStringOperatorsProvider')]
    public function validStringOperators(string $operator, string $value): void
    {
        $spec = new ComparisonSpecification(column: 'col', value: $value, operator: $operator);

        Assert::same($spec->getOperator(), $operator);
        Assert::same($spec->getValue(), $value);
    }

    /** @return list<array{string, int|bool|array<never, never>}> */
    public static function invalidStringOperatorsProvider(): array
    {
        return [
            ['like', 123],
            ['like', true],
            ['like', []],
            ['not like', 123],
            ['not like', true],
            ['not like', []],
            ['ilike', 123],
            ['ilike', true],
            ['ilike', []],
            ['not ilike', 123],
            ['not ilike', true],
            ['not ilike', []],
        ];
    }

    #[DataProvider('invalidStringOperatorsProvider')]
    public function stringOperatorsRequireString(string $operator, int|bool|array $value): void
    {
        Expect::exception(InvalidArgumentException::class)->withMessageContaining('requires string value');

        new ComparisonSpecification(column: 'col', value: $value, operator: $operator);
    }

    // ---------- Common operators ----------

    /** @return list<array{string, string|int|float|bool|null}> */
    public static function generalOperatorsProvider(): array
    {
        return [
            ['=', 123],
            ['=', 'string'],
            ['=', true],
            ['=', null],
            ['!=', 123],
            ['!=', 'string'],
            ['!=', true],
            ['!=', null],
            ['<>', 123],
            ['<>', null],
            ['>', 123],
            ['>', 1.5],
            ['>=', 123],
            ['<', 123],
            ['<=', 123],
        ];
    }

    #[DataProvider('generalOperatorsProvider')]
    public function generalOperators(string $operator, string|int|float|bool|null $value): void
    {
        $spec = new ComparisonSpecification(column: 'col', value: $value, operator: $operator);

        Assert::same($spec->getOperator(), $operator);
        Assert::same($spec->getValue(), $value);
    }

    public function operatorCaseInsensitivity(): void
    {
        $spec = new ComparisonSpecification(column: 'col', value: 'test', operator: 'LIKE');
        Assert::same($spec->getOperator(), 'like');

        $spec2 = new ComparisonSpecification(column: 'col', value: [1, 10], operator: 'BETWEEN');
        Assert::same($spec2->getOperator(), 'between');
    }

    // ---------- Factory methods ----------

    public function factoryMethods(): void
    {
        $equal = ComparisonSpecification::equal(column: 'col', value: 'val');
        Assert::same($equal->getOperator(), '=');
        Assert::same($equal->getValue(), 'val');

        $notEqual = ComparisonSpecification::notEqual(column: 'col', value: 'val');
        Assert::same($notEqual->getOperator(), '!=');

        $greaterThan = ComparisonSpecification::greaterThan(column: 'col', value: 5);
        Assert::same($greaterThan->getOperator(), '>');

        $greaterThanOrEqual = ComparisonSpecification::greaterThanOrEqual(column: 'col', value: 5);
        Assert::same($greaterThanOrEqual->getOperator(), '>=');

        $lessThan = ComparisonSpecification::lessThan(column: 'col', value: 5);
        Assert::same($lessThan->getOperator(), '<');

        $lessThanOrEqual = ComparisonSpecification::lessThanOrEqual(column: 'col', value: 5);
        Assert::same($lessThanOrEqual->getOperator(), '<=');

        $like = ComparisonSpecification::like(column: 'col', pattern: '%pattern%');
        Assert::same($like->getOperator(), 'like');

        $notLike = ComparisonSpecification::notLike(column: 'col', pattern: '%pattern%');
        Assert::same($notLike->getOperator(), 'not like');

        $ilike = ComparisonSpecification::ilike(column: 'col', pattern: '%pattern%');
        Assert::same($ilike->getOperator(), 'ilike');

        $notIlike = ComparisonSpecification::notIlike(column: 'col', pattern: '%pattern%');
        Assert::same($notIlike->getOperator(), 'not ilike');

        $in = ComparisonSpecification::in(column: 'col', values: [1, 2, 3]);
        Assert::same($in->getOperator(), 'in');
        Assert::same($in->getValue(), [1, 2, 3]);

        $notIn = ComparisonSpecification::notIn(column: 'col', values: [1, 2, 3]);
        Assert::same($notIn->getOperator(), 'not in');

        $between = ComparisonSpecification::between(column: 'col', from: 10, to: 20);
        Assert::same($between->getOperator(), 'between');
        Assert::same($between->getValue(), [10, 20]);

        $notBetween = ComparisonSpecification::notBetween(column: 'col', from: 10, to: 20);
        Assert::same($notBetween->getOperator(), 'not between');

        $isNull = ComparisonSpecification::isNull(column: 'col');
        Assert::same($isNull->getOperator(), 'is');
        Assert::null($isNull->getValue());

        $isNotNull = ComparisonSpecification::isNotNull(column: 'col');
        Assert::same($isNotNull->getOperator(), 'is not');
        Assert::null($isNotNull->getValue());

        $startsWith = ComparisonSpecification::startsWith(column: 'col', prefix: 'pre');
        Assert::same($startsWith->getOperator(), 'like');
        Assert::same($startsWith->getValue(), 'pre%');

        $endsWith = ComparisonSpecification::endsWith(column: 'col', suffix: 'suf');
        Assert::same($endsWith->getOperator(), 'like');
        Assert::same($endsWith->getValue(), '%suf');

        $contains = ComparisonSpecification::contains(column: 'col', substring: 'sub');
        Assert::same($contains->getOperator(), 'like');
        Assert::same($contains->getValue(), '%sub%');
    }

    public function stringOperators(): void
    {
        $like = ComparisonSpecification::like(column: 'name', pattern: '%john%');
        Assert::same($like->getOperator(), 'like');

        $notLike = ComparisonSpecification::notLike(column: 'name', pattern: '%admin%');
        Assert::same($notLike->getOperator(), 'not like');

        $ilike = ComparisonSpecification::ilike(column: 'name', pattern: '%john%');
        Assert::same($ilike->getOperator(), 'ilike');

        $notIlike = ComparisonSpecification::notIlike(column: 'name', pattern: '%admin%');
        Assert::same($notIlike->getOperator(), 'not ilike');
    }

    public function patternHelpers(): void
    {
        $startsWith = ComparisonSpecification::startsWith(column: 'name', prefix: 'John');
        Assert::same($startsWith->getOperator(), 'like');
        Assert::same($startsWith->getValue(), 'John%');

        $endsWith = ComparisonSpecification::endsWith(column: 'name', suffix: 'Doe');
        Assert::same($endsWith->getValue(), '%Doe');

        $contains = ComparisonSpecification::contains(column: 'name', substring: 'oh');
        Assert::same($contains->getValue(), '%oh%');
    }

    public function arrayOperators(): void
    {
        $in = ComparisonSpecification::in(column: 'status', values: ['active', 'pending']);
        Assert::same($in->getOperator(), 'in');
        Assert::same($in->getValue(), ['active', 'pending']);

        $notIn = ComparisonSpecification::notIn(column: 'status', values: ['deleted', 'archived']);
        Assert::same($notIn->getOperator(), 'not in');

        $between = ComparisonSpecification::between(column: 'age', from: 18, to: 65);
        Assert::same($between->getOperator(), 'between');
        Assert::same($between->getValue(), [18, 65]);

        $notBetween = ComparisonSpecification::notBetween(column: 'age', from: 18, to: 65);
        Assert::same($notBetween->getOperator(), 'not between');
    }

    public function nullOperators(): void
    {
        $isNull = ComparisonSpecification::isNull(column: 'deleted_at');
        Assert::same($isNull->getOperator(), 'is');
        Assert::null($isNull->getValue());

        $isNotNull = ComparisonSpecification::isNotNull(column: 'deleted_at');
        Assert::same($isNotNull->getOperator(), 'is not');
        Assert::null($isNotNull->getValue());
    }

    public function validationWithNullValue(): void
    {
        Expect::exception(InvalidArgumentException::class)->withMessageContaining('Operator ">" cannot be used with NULL value');

        new ComparisonSpecification(column: 'age', value: null, operator: '>');
    }

    public function validationArrayOperatorsRequireArray(): void
    {
        Expect::exception(InvalidArgumentException::class)->withMessageContaining('Operator "in" requires array value');

        new ComparisonSpecification(column: 'status', value: 'active', operator: 'in');
    }

    public function validationBetweenRequiresTwoValues(): void
    {
        Expect::exception(InvalidArgumentException::class)->withMessageContaining('Operator "between" requires array with exactly two values');

        new ComparisonSpecification(column: 'age', value: [18], operator: 'between');
    }

    public function validationStringOperatorsRequireString(): void
    {
        Expect::exception(InvalidArgumentException::class)->withMessageContaining('Operator "like" requires string value');

        new ComparisonSpecification(column: 'name', value: 123, operator: 'like');
    }
}
