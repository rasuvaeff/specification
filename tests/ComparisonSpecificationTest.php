<?php

declare(strict_types=1);

namespace Rasuvaeff\Specification\Tests;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Rasuvaeff\Specification\ComparisonSpecification;
use Rasuvaeff\Specification\SpecificationVisitor;

#[CoversClass(ComparisonSpecification::class)]
final class ComparisonSpecificationTest extends TestCase
{
    #[Test]
    public function constructorWithValidOperator(): void
    {
        $spec = new ComparisonSpecification(
            column: 'age',
            value: 25,
            operator: '>',
        );

        $this->assertSame('age', $spec->getColumn());
        $this->assertSame(25, $spec->getValue());
        $this->assertSame('>', $spec->getOperator());
    }

    #[Test]
    public function constructorWithDefaultOperator(): void
    {
        $spec = new ComparisonSpecification(
            column: 'name',
            value: 'John',
        );

        $this->assertSame('=', $spec->getOperator());
    }

    #[Test]
    public function throwsExceptionForInvalidOperator(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid operator "invalid_op"');

        new ComparisonSpecification(
            column: 'age',
            value: 25,
            operator: 'invalid_op',
        );
    }

    #[Test]
    public function acceptsVisitor(): void
    {
        $spec = new ComparisonSpecification(column: 'age', value: 25);
        $visitor = $this->createMock(SpecificationVisitor::class);

        $visitor->expects($this->once())
            ->method('visitComparison')
            ->with($spec)
            ->willReturn('result');

        $result = $spec->accept(visitor: $visitor);
        $this->assertSame('result', $result);
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
    #[Test]
    public function nullValueAllowed(string $operator): void
    {
        $spec = new ComparisonSpecification(column: 'col', value: null, operator: $operator);
        $this->assertSame($operator, $spec->getOperator());
        $this->assertNull($spec->getValue());
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
    #[Test]
    public function nullValueDisallowed(string $operator): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('cannot be used with NULL value');
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
    #[Test]
    public function validArrayOperators(string $operator, array $value): void
    {
        $spec = new ComparisonSpecification(column: 'col', value: $value, operator: $operator);
        $this->assertSame($operator, $spec->getOperator());
        $this->assertSame($value, $spec->getValue());
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
    #[Test]
    public function arrayOperatorsRequireArray(string $operator, string|int|bool $value): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('requires array value');
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
    #[Test]
    public function betweenRequiresExactlyTwoElements(string $operator, array $value): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('requires array with exactly two values');
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
    #[Test]
    public function validStringOperators(string $operator, string $value): void
    {
        $spec = new ComparisonSpecification(column: 'col', value: $value, operator: $operator);
        $this->assertSame($operator, $spec->getOperator());
        $this->assertSame($value, $spec->getValue());
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
    #[Test]
    public function stringOperatorsRequireString(string $operator, int|bool|array $value): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('requires string value');
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
    #[Test]
    public function generalOperators(string $operator, string|int|float|bool|null $value): void
    {
        $spec = new ComparisonSpecification(column: 'col', value: $value, operator: $operator);
        $this->assertSame($operator, $spec->getOperator());
        $this->assertSame($value, $spec->getValue());
    }

    #[Test]
    public function operatorCaseInsensitivity(): void
    {
        // LIKE accepts a string value
        $spec = new ComparisonSpecification(column: 'col', value: 'test', operator: 'LIKE');
        $this->assertSame('like', $spec->getOperator());

        $spec2 = new ComparisonSpecification(column: 'col', value: [1, 10], operator: 'BETWEEN');
        $this->assertSame('between', $spec2->getOperator());
    }

    // ---------- Factory methods ----------

    #[Test]
    public function factoryMethods(): void
    {
        $equal = ComparisonSpecification::equal(column: 'col', value: 'val');
        $this->assertSame('=', $equal->getOperator());
        $this->assertSame('val', $equal->getValue());

        $notEqual = ComparisonSpecification::notEqual(column: 'col', value: 'val');
        $this->assertSame('!=', $notEqual->getOperator());

        $greaterThan = ComparisonSpecification::greaterThan(column: 'col', value: 5);
        $this->assertSame('>', $greaterThan->getOperator());

        $greaterThanOrEqual = ComparisonSpecification::greaterThanOrEqual(column: 'col', value: 5);
        $this->assertSame('>=', $greaterThanOrEqual->getOperator());

        $lessThan = ComparisonSpecification::lessThan(column: 'col', value: 5);
        $this->assertSame('<', $lessThan->getOperator());

        $lessThanOrEqual = ComparisonSpecification::lessThanOrEqual(column: 'col', value: 5);
        $this->assertSame('<=', $lessThanOrEqual->getOperator());

        $like = ComparisonSpecification::like(column: 'col', pattern: '%pattern%');
        $this->assertSame('like', $like->getOperator());

        $notLike = ComparisonSpecification::notLike(column: 'col', pattern: '%pattern%');
        $this->assertSame('not like', $notLike->getOperator());

        $ilike = ComparisonSpecification::ilike(column: 'col', pattern: '%pattern%');
        $this->assertSame('ilike', $ilike->getOperator());

        $notIlike = ComparisonSpecification::notIlike(column: 'col', pattern: '%pattern%');
        $this->assertSame('not ilike', $notIlike->getOperator());

        $in = ComparisonSpecification::in(column: 'col', values: [1, 2, 3]);
        $this->assertSame('in', $in->getOperator());
        $this->assertSame([1, 2, 3], $in->getValue());

        $notIn = ComparisonSpecification::notIn(column: 'col', values: [1, 2, 3]);
        $this->assertSame('not in', $notIn->getOperator());

        $between = ComparisonSpecification::between(column: 'col', from: 10, to: 20);
        $this->assertSame('between', $between->getOperator());
        $this->assertSame([10, 20], $between->getValue());

        $notBetween = ComparisonSpecification::notBetween(column: 'col', from: 10, to: 20);
        $this->assertSame('not between', $notBetween->getOperator());

        $isNull = ComparisonSpecification::isNull(column: 'col');
        $this->assertSame('is', $isNull->getOperator());
        $this->assertNull($isNull->getValue());

        $isNotNull = ComparisonSpecification::isNotNull(column: 'col');
        $this->assertSame('is not', $isNotNull->getOperator());
        $this->assertNull($isNotNull->getValue());

        $startsWith = ComparisonSpecification::startsWith(column: 'col', prefix: 'pre');
        $this->assertSame('like', $startsWith->getOperator());
        $this->assertSame('pre%', $startsWith->getValue());

        $endsWith = ComparisonSpecification::endsWith(column: 'col', suffix: 'suf');
        $this->assertSame('like', $endsWith->getOperator());
        $this->assertSame('%suf', $endsWith->getValue());

        $contains = ComparisonSpecification::contains(column: 'col', substring: 'sub');
        $this->assertSame('like', $contains->getOperator());
        $this->assertSame('%sub%', $contains->getValue());
    }

    #[Test]
    public function stringOperators(): void
    {
        $like = ComparisonSpecification::like(column: 'name', pattern: '%john%');
        $this->assertSame('like', $like->getOperator());

        $notLike = ComparisonSpecification::notLike(column: 'name', pattern: '%admin%');
        $this->assertSame('not like', $notLike->getOperator());

        $ilike = ComparisonSpecification::ilike(column: 'name', pattern: '%john%');
        $this->assertSame('ilike', $ilike->getOperator());

        $notIlike = ComparisonSpecification::notIlike(column: 'name', pattern: '%admin%');
        $this->assertSame('not ilike', $notIlike->getOperator());
    }

    #[Test]
    public function patternHelpers(): void
    {
        $startsWith = ComparisonSpecification::startsWith(column: 'name', prefix: 'John');
        $this->assertSame('like', $startsWith->getOperator());
        $this->assertSame('John%', $startsWith->getValue());

        $endsWith = ComparisonSpecification::endsWith(column: 'name', suffix: 'Doe');
        $this->assertSame('%Doe', $endsWith->getValue());

        $contains = ComparisonSpecification::contains(column: 'name', substring: 'oh');
        $this->assertSame('%oh%', $contains->getValue());
    }

    #[Test]
    public function arrayOperators(): void
    {
        $in = ComparisonSpecification::in(column: 'status', values: ['active', 'pending']);
        $this->assertSame('in', $in->getOperator());
        $this->assertSame(['active', 'pending'], $in->getValue());

        $notIn = ComparisonSpecification::notIn(column: 'status', values: ['deleted', 'archived']);
        $this->assertSame('not in', $notIn->getOperator());

        $between = ComparisonSpecification::between(column: 'age', from: 18, to: 65);
        $this->assertSame('between', $between->getOperator());
        $this->assertSame([18, 65], $between->getValue());

        $notBetween = ComparisonSpecification::notBetween(column: 'age', from: 18, to: 65);
        $this->assertSame('not between', $notBetween->getOperator());
    }

    #[Test]
    public function nullOperators(): void
    {
        $isNull = ComparisonSpecification::isNull(column: 'deleted_at');
        $this->assertSame('is', $isNull->getOperator());
        $this->assertNull($isNull->getValue());

        $isNotNull = ComparisonSpecification::isNotNull(column: 'deleted_at');
        $this->assertSame('is not', $isNotNull->getOperator());
        $this->assertNull($isNotNull->getValue());
    }

    #[Test]
    public function validationWithNullValue(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Operator ">" cannot be used with NULL value');

        new ComparisonSpecification(column: 'age', value: null, operator: '>');
    }

    #[Test]
    public function validationArrayOperatorsRequireArray(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Operator "in" requires array value');

        new ComparisonSpecification(column: 'status', value: 'active', operator: 'in');
    }

    #[Test]
    public function validationBetweenRequiresTwoValues(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Operator "between" requires array with exactly two values');

        new ComparisonSpecification(column: 'age', value: [18], operator: 'between');
    }

    #[Test]
    public function validationStringOperatorsRequireString(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Operator "like" requires string value');

        new ComparisonSpecification(column: 'name', value: 123, operator: 'like');
    }
}
