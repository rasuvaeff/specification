<?php

declare(strict_types=1);

namespace Rasuvaeff\Specification\Tests;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Rasuvaeff\Specification\OrConditionSpecification;

#[CoversClass(OrConditionSpecification::class)]
final class OrConditionSpecificationTest extends TestCase
{
    #[Test]
    public function fromArrayWithSimpleEquality(): void
    {
        $spec = OrConditionSpecification::fromArray(conditions: [
            'status' => 'active',
            'type' => 'email',
        ]);

        $conditions = $spec->getConditions();

        $this->assertCount(2, $conditions);
        $this->assertEquals(['status' => 'active'], $conditions[0]);
        $this->assertEquals(['type' => 'email'], $conditions[1]);
    }

    #[Test]
    public function fromArrayWithOperatorTwoElements(): void
    {
        $spec = OrConditionSpecification::fromArray(conditions: [
            'age' => ['>', 18],
            'type' => ['!=', 'spam'],
        ]);

        $conditions = $spec->getConditions();

        $this->assertCount(2, $conditions);

        // Verify the canonical format is produced: ['operator', 'column', 'value']
        $this->assertEquals(['>', 'age', 18], $conditions[0]);
        $this->assertEquals(['!=', 'type', 'spam'], $conditions[1]);
    }

    #[Test]
    public function fromArrayWithOperatorThreeElements(): void
    {

        $spec = OrConditionSpecification::fromArray(conditions: [
            'age' => ['>', 18],  // fromArray should produce ['>', 'age', 18]
            'score' => ['between', 80, 100],  // fromArray should produce ['between', 'score', 80, 100]
        ]);

        $conditions = $spec->getConditions();

        $this->assertCount(2, $conditions);
        $this->assertEquals(['>', 'age', 18], $conditions[0]);
        $this->assertEquals(['between', 'score', 80, 100], $conditions[1]);
    }

    #[Test]
    public function fromArrayMixedConditions(): void
    {
        $spec = OrConditionSpecification::fromArray(conditions: [
            'status' => 'active',
            'age' => ['>', 18],
            'name' => ['like', '%john%'],
            'type' => ['in', ['email', 'sms']],
        ]);

        $conditions = $spec->getConditions();

        $this->assertCount(4, $conditions);
        $this->assertEquals(['status' => 'active'], $conditions[0]);
        $this->assertEquals(['>', 'age', 18], $conditions[1]);
        $this->assertEquals(['like', 'name', '%john%'], $conditions[2]);
        $this->assertEquals(['in', 'type', ['email', 'sms']], $conditions[3]);
    }

    #[Test]
    public function directConstruction(): void
    {
        $spec = new OrConditionSpecification(conditions: [
            ['status' => 'active'],
            ['>', 'age', 18],
            ['!=', 'type', 'spam'],
        ]);

        $conditions = $spec->getConditions();

        $this->assertCount(3, $conditions);
        $this->assertEquals(['status' => 'active'], $conditions[0]);
        $this->assertEquals(['>', 'age', 18], $conditions[1]);
        $this->assertEquals(['!=', 'type', 'spam'], $conditions[2]);
    }

    #[Test]
    public function emptyConditions(): void
    {
        $spec = new OrConditionSpecification(conditions: []);

        $conditions = $spec->getConditions();

        $this->assertEmpty($conditions);
    }

    #[Test]
    public function fromArrayWithNotBetweenSimplifiedFormat(): void
    {
        $spec = OrConditionSpecification::fromArray(conditions: [
            'age' => ['not between', 18, 65],
        ]);

        $conditions = $spec->getConditions();
        $this->assertCount(1, $conditions);
        $this->assertEquals(['not between', 'age', 18, 65], $conditions[0]);
    }

    #[Test]
    public function fromArrayWithNotInSimplifiedFormat(): void
    {
        $spec = OrConditionSpecification::fromArray(conditions: [
            'status' => ['not in', ['banned', 'deleted']],
        ]);

        $conditions = $spec->getConditions();
        $this->assertCount(1, $conditions);
        $this->assertEquals(['not in', 'status', ['banned', 'deleted']], $conditions[0]);
    }

    #[Test]
    public function fromArrayWithBetweenFullFormat(): void
    {
        $spec = OrConditionSpecification::fromArray(conditions: [
            'age' => ['between', 'age', 18, 65],
        ]);

        $conditions = $spec->getConditions();
        $this->assertCount(1, $conditions);
        $this->assertEquals(['between', 'age', 18, 65], $conditions[0]);
    }

    #[Test]
    public function fromArrayWithInFullFormat(): void
    {
        $spec = OrConditionSpecification::fromArray(conditions: [
            'status' => ['in', 'status', ['active', 'pending']],
        ]);

        $conditions = $spec->getConditions();
        $this->assertCount(1, $conditions);
        $this->assertEquals(['in', 'status', ['active', 'pending']], $conditions[0]);
    }

    #[Test]
    public function fromArrayWithBetweenInvalidElementCount(): void
    {
        $spec = OrConditionSpecification::fromArray(conditions: [
            'age' => ['between', 18],
        ]);

        $conditions = $spec->getConditions();
        $this->assertCount(1, $conditions);
        $this->assertEquals(['age' => ['between', 18]], $conditions[0]);
    }

    #[Test]
    public function fromArrayWithInInvalidFormat(): void
    {
        $spec = OrConditionSpecification::fromArray(conditions: [
            'status' => ['in', 'not_array'],
        ]);

        $conditions = $spec->getConditions();
        $this->assertCount(1, $conditions);
        $this->assertEquals(['status' => ['in', 'not_array']], $conditions[0]);
    }

    #[Test]
    public function fromArrayWithOperatorThreeElementsMatchingColumn(): void
    {
        $spec = OrConditionSpecification::fromArray(conditions: [
            'age' => ['>', 'age', 18],
        ]);

        $conditions = $spec->getConditions();
        $this->assertCount(1, $conditions);
        $this->assertEquals(['>', 'age', 18], $conditions[0]);
    }

    #[Test]
    public function fromArrayWithOperatorInvalidElementCount(): void
    {
        $spec = OrConditionSpecification::fromArray(conditions: [
            'age' => ['>', 18, 20, 30],
        ]);

        $conditions = $spec->getConditions();
        $this->assertCount(1, $conditions);
        $this->assertEquals(['age' => ['>', 18, 20, 30]], $conditions[0]);
    }

    #[Test]
    public function fromArrayWithNonStringArrayKey(): void
    {
        $spec = OrConditionSpecification::fromArray(conditions: [
            'status' => 'active',
        ]);

        $conditions = $spec->getConditions();
        $this->assertCount(1, $conditions);
        $this->assertEquals(['status' => 'active'], $conditions[0]);
    }

    #[Test]
    public function acceptsVisitor(): void
    {
        $spec = new OrConditionSpecification(conditions: [['status' => 'active']]);
        $visitor = $this->createMock(\Rasuvaeff\Specification\SpecificationVisitor::class);

        $visitor->expects($this->once())
            ->method('visitOrCondition')
            ->with($spec)
            ->willReturn('result');

        $result = $spec->accept(visitor: $visitor);
        $this->assertSame('result', $result);
    }

    #[Test]
    public function fromArrayWithNotInFullFormat(): void
    {
        $spec = OrConditionSpecification::fromArray(conditions: [
            'status' => ['not in', 'status', ['banned']],
        ]);

        $conditions = $spec->getConditions();
        $this->assertCount(1, $conditions);
        $this->assertEquals(['not in', 'status', ['banned']], $conditions[0]);
    }

    #[Test]
    public function fromArrayWithInSimplifiedArrayValues(): void
    {
        $spec = OrConditionSpecification::fromArray(conditions: [
            'status' => ['in', ['a', 'b']],
        ]);

        $conditions = $spec->getConditions();
        $this->assertCount(1, $conditions);
        $this->assertEquals(['in', 'status', ['a', 'b']], $conditions[0]);
    }

    #[Test]
    public function fromArrayWithPlainStringListBecomesEquality(): void
    {
        // A list of plain string values is not an operator array — it stays a hash
        // and yiisoft turns it into an IN condition.
        $spec = OrConditionSpecification::fromArray(conditions: [
            'name' => ['alice', 'bob'],
        ]);

        $conditions = $spec->getConditions();
        $this->assertCount(1, $conditions);
        $this->assertEquals(['name' => ['alice', 'bob']], $conditions[0]);
    }

    #[Test]
    public function fromArrayWithUnknownOperatorBecomesEquality(): void
    {
        $spec = OrConditionSpecification::fromArray(conditions: [
            'status' => ['unknownop', 'x'],
        ]);

        $conditions = $spec->getConditions();
        $this->assertCount(1, $conditions);
        $this->assertEquals(['status' => ['unknownop', 'x']], $conditions[0]);
    }

    #[Test]
    public function fromArrayNormalizesOperatorCase(): void
    {
        $spec = OrConditionSpecification::fromArray(conditions: [
            'age' => ['BETWEEN', 18, 65],
            'type' => ['IN', ['a', 'b']],
            'score' => ['>', 10],
        ]);

        $conditions = $spec->getConditions();
        $this->assertEquals(['between', 'age', 18, 65], $conditions[0]);
        $this->assertEquals(['in', 'type', ['a', 'b']], $conditions[1]);
        $this->assertEquals(['>', 'score', 10], $conditions[2]);
    }

    #[Test]
    public function fromArrayWithNotInCanonicalFormatDistinctFromDefault(): void
    {
        $spec = OrConditionSpecification::fromArray(conditions: [
            'status' => ['not in', 'status', ['x', 'y']],
        ]);

        $conditions = $spec->getConditions();
        $this->assertCount(1, $conditions);
        $this->assertSame(['not in', 'status', ['x', 'y']], $conditions[0]);
    }

    #[Test]
    public function fromArrayWithNotInCanonicalNonArrayValuesFallsBack(): void
    {
        $spec = OrConditionSpecification::fromArray(conditions: [
            'status' => ['not in', 'status', 'not_an_array'],
        ]);

        $conditions = $spec->getConditions();
        $this->assertCount(1, $conditions);
        $this->assertSame(['status' => ['not in', 'status', 'not_an_array']], $conditions[0]);
    }

    #[Test]
    public function fromArrayWithNotBetweenCanonicalFormatDistinctFromDefault(): void
    {
        $spec = OrConditionSpecification::fromArray(conditions: [
            'age' => ['not between', 'age', 18, 65],
        ]);

        $conditions = $spec->getConditions();
        $this->assertCount(1, $conditions);
        $this->assertSame(['not between', 'age', 18, 65], $conditions[0]);
    }

    #[Test]
    public function fromArrayWithIntegerFirstElementNotRecognized(): void
    {
        $spec = OrConditionSpecification::fromArray(conditions: [
            'col' => [0, 'val'],
        ]);

        $conditions = $spec->getConditions();
        $this->assertCount(1, $conditions);
        $this->assertEquals(['col' => [0, 'val']], $conditions[0]);
    }

    #[Test]
    public function fromArrayWithBetweenWrongColumnInCanonical(): void
    {
        $spec = OrConditionSpecification::fromArray(conditions: [
            'age' => ['between', 'other_col', 1, 10],
        ]);

        $conditions = $spec->getConditions();
        $this->assertCount(1, $conditions);
        $this->assertEquals(['age' => ['between', 'other_col', 1, 10]], $conditions[0]);
    }

    #[Test]
    public function fromArrayWithDefaultOperatorCanonicalWrongColumn(): void
    {
        $spec = OrConditionSpecification::fromArray(conditions: [
            'age' => ['>', 'other_col', 18],
        ]);

        $conditions = $spec->getConditions();
        $this->assertCount(1, $conditions);
        $this->assertEquals(['age' => ['>', 'other_col', 18]], $conditions[0]);
    }

    #[Test]
    public function fromArrayWithNonArrayValueTreatedAsEquality(): void
    {
        $spec = OrConditionSpecification::fromArray(conditions: [
            'status' => 'active',
            'count' => 5,
        ]);

        $conditions = $spec->getConditions();
        $this->assertCount(2, $conditions);
        $this->assertEquals(['status' => 'active'], $conditions[0]);
        $this->assertEquals(['count' => 5], $conditions[1]);
    }
}
