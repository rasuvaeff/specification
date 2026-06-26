<?php

declare(strict_types=1);

namespace Rasuvaeff\Specification\Tests;

use Rasuvaeff\Specification\OrConditionSpecification;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Test;

#[Test]
#[Covers(OrConditionSpecification::class)]
final class OrConditionSpecificationTest
{
    public function fromArrayWithSimpleEquality(): void
    {
        $spec = OrConditionSpecification::fromArray(conditions: [
            'status' => 'active',
            'type' => 'email',
        ]);

        $conditions = $spec->getConditions();

        Assert::count($conditions, 2);
        Assert::equals($conditions[0], ['status' => 'active']);
        Assert::equals($conditions[1], ['type' => 'email']);
    }

    public function fromArrayWithOperatorTwoElements(): void
    {
        $spec = OrConditionSpecification::fromArray(conditions: [
            'age' => ['>', 18],
            'type' => ['!=', 'spam'],
        ]);

        $conditions = $spec->getConditions();

        Assert::count($conditions, 2);
        Assert::equals($conditions[0], ['>', 'age', 18]);
        Assert::equals($conditions[1], ['!=', 'type', 'spam']);
    }

    public function fromArrayWithOperatorThreeElements(): void
    {
        $spec = OrConditionSpecification::fromArray(conditions: [
            'age' => ['>', 18],
            'score' => ['between', 80, 100],
        ]);

        $conditions = $spec->getConditions();

        Assert::count($conditions, 2);
        Assert::equals($conditions[0], ['>', 'age', 18]);
        Assert::equals($conditions[1], ['between', 'score', 80, 100]);
    }

    public function fromArrayMixedConditions(): void
    {
        $spec = OrConditionSpecification::fromArray(conditions: [
            'status' => 'active',
            'age' => ['>', 18],
            'name' => ['like', '%john%'],
            'type' => ['in', ['email', 'sms']],
        ]);

        $conditions = $spec->getConditions();

        Assert::count($conditions, 4);
        Assert::equals($conditions[0], ['status' => 'active']);
        Assert::equals($conditions[1], ['>', 'age', 18]);
        Assert::equals($conditions[2], ['like', 'name', '%john%']);
        Assert::equals($conditions[3], ['in', 'type', ['email', 'sms']]);
    }

    public function directConstruction(): void
    {
        $spec = new OrConditionSpecification(conditions: [
            ['status' => 'active'],
            ['>', 'age', 18],
            ['!=', 'type', 'spam'],
        ]);

        $conditions = $spec->getConditions();

        Assert::count($conditions, 3);
        Assert::equals($conditions[0], ['status' => 'active']);
        Assert::equals($conditions[1], ['>', 'age', 18]);
        Assert::equals($conditions[2], ['!=', 'type', 'spam']);
    }

    public function emptyConditions(): void
    {
        $spec = new OrConditionSpecification(conditions: []);

        Assert::blank($spec->getConditions());
    }

    public function fromArrayWithNotBetweenSimplifiedFormat(): void
    {
        $spec = OrConditionSpecification::fromArray(conditions: [
            'age' => ['not between', 18, 65],
        ]);

        $conditions = $spec->getConditions();
        Assert::count($conditions, 1);
        Assert::equals($conditions[0], ['not between', 'age', 18, 65]);
    }

    public function fromArrayWithNotInSimplifiedFormat(): void
    {
        $spec = OrConditionSpecification::fromArray(conditions: [
            'status' => ['not in', ['banned', 'deleted']],
        ]);

        $conditions = $spec->getConditions();
        Assert::count($conditions, 1);
        Assert::equals($conditions[0], ['not in', 'status', ['banned', 'deleted']]);
    }

    public function fromArrayWithBetweenFullFormat(): void
    {
        $spec = OrConditionSpecification::fromArray(conditions: [
            'age' => ['between', 'age', 18, 65],
        ]);

        $conditions = $spec->getConditions();
        Assert::count($conditions, 1);
        Assert::equals($conditions[0], ['between', 'age', 18, 65]);
    }

    public function fromArrayWithInFullFormat(): void
    {
        $spec = OrConditionSpecification::fromArray(conditions: [
            'status' => ['in', 'status', ['active', 'pending']],
        ]);

        $conditions = $spec->getConditions();
        Assert::count($conditions, 1);
        Assert::equals($conditions[0], ['in', 'status', ['active', 'pending']]);
    }

    public function fromArrayWithBetweenInvalidElementCount(): void
    {
        $spec = OrConditionSpecification::fromArray(conditions: [
            'age' => ['between', 18],
        ]);

        $conditions = $spec->getConditions();
        Assert::count($conditions, 1);
        Assert::equals($conditions[0], ['age' => ['between', 18]]);
    }

    public function fromArrayWithInInvalidFormat(): void
    {
        $spec = OrConditionSpecification::fromArray(conditions: [
            'status' => ['in', 'not_array'],
        ]);

        $conditions = $spec->getConditions();
        Assert::count($conditions, 1);
        Assert::equals($conditions[0], ['status' => ['in', 'not_array']]);
    }

    public function fromArrayWithOperatorThreeElementsMatchingColumn(): void
    {
        $spec = OrConditionSpecification::fromArray(conditions: [
            'age' => ['>', 'age', 18],
        ]);

        $conditions = $spec->getConditions();
        Assert::count($conditions, 1);
        Assert::equals($conditions[0], ['>', 'age', 18]);
    }

    public function fromArrayWithOperatorInvalidElementCount(): void
    {
        $spec = OrConditionSpecification::fromArray(conditions: [
            'age' => ['>', 18, 20, 30],
        ]);

        $conditions = $spec->getConditions();
        Assert::count($conditions, 1);
        Assert::equals($conditions[0], ['age' => ['>', 18, 20, 30]]);
    }

    public function fromArrayWithNonStringArrayKey(): void
    {
        $spec = OrConditionSpecification::fromArray(conditions: [
            'status' => 'active',
        ]);

        $conditions = $spec->getConditions();
        Assert::count($conditions, 1);
        Assert::equals($conditions[0], ['status' => 'active']);
    }

    public function acceptsVisitor(): void
    {
        $spec = new OrConditionSpecification(conditions: [['status' => 'active']]);
        $visitor = new FakeVisitor(returnValue: 'result');

        $result = $spec->accept(visitor: $visitor);

        Assert::same($visitor->lastMethod, 'visitOrCondition');
        Assert::same($visitor->lastArg, $spec);
        Assert::same($result, 'result');
    }

    public function fromArrayWithNotInFullFormat(): void
    {
        $spec = OrConditionSpecification::fromArray(conditions: [
            'status' => ['not in', 'status', ['banned']],
        ]);

        $conditions = $spec->getConditions();
        Assert::count($conditions, 1);
        Assert::equals($conditions[0], ['not in', 'status', ['banned']]);
    }

    public function fromArrayWithInSimplifiedArrayValues(): void
    {
        $spec = OrConditionSpecification::fromArray(conditions: [
            'status' => ['in', ['a', 'b']],
        ]);

        $conditions = $spec->getConditions();
        Assert::count($conditions, 1);
        Assert::equals($conditions[0], ['in', 'status', ['a', 'b']]);
    }

    public function fromArrayWithPlainStringListBecomesEquality(): void
    {
        $spec = OrConditionSpecification::fromArray(conditions: [
            'name' => ['alice', 'bob'],
        ]);

        $conditions = $spec->getConditions();
        Assert::count($conditions, 1);
        Assert::equals($conditions[0], ['name' => ['alice', 'bob']]);
    }

    public function fromArrayWithUnknownOperatorBecomesEquality(): void
    {
        $spec = OrConditionSpecification::fromArray(conditions: [
            'status' => ['unknownop', 'x'],
        ]);

        $conditions = $spec->getConditions();
        Assert::count($conditions, 1);
        Assert::equals($conditions[0], ['status' => ['unknownop', 'x']]);
    }

    public function fromArrayNormalizesOperatorCase(): void
    {
        $spec = OrConditionSpecification::fromArray(conditions: [
            'age' => ['BETWEEN', 18, 65],
            'type' => ['IN', ['a', 'b']],
            'score' => ['>', 10],
        ]);

        $conditions = $spec->getConditions();
        Assert::equals($conditions[0], ['between', 'age', 18, 65]);
        Assert::equals($conditions[1], ['in', 'type', ['a', 'b']]);
        Assert::equals($conditions[2], ['>', 'score', 10]);
    }

    public function fromArrayWithNotInCanonicalFormatDistinctFromDefault(): void
    {
        $spec = OrConditionSpecification::fromArray(conditions: [
            'status' => ['not in', 'status', ['x', 'y']],
        ]);

        $conditions = $spec->getConditions();
        Assert::count($conditions, 1);
        Assert::same($conditions[0], ['not in', 'status', ['x', 'y']]);
    }

    public function fromArrayWithNotInCanonicalNonArrayValuesFallsBack(): void
    {
        $spec = OrConditionSpecification::fromArray(conditions: [
            'status' => ['not in', 'status', 'not_an_array'],
        ]);

        $conditions = $spec->getConditions();
        Assert::count($conditions, 1);
        Assert::same($conditions[0], ['status' => ['not in', 'status', 'not_an_array']]);
    }

    public function fromArrayWithNotBetweenCanonicalFormatDistinctFromDefault(): void
    {
        $spec = OrConditionSpecification::fromArray(conditions: [
            'age' => ['not between', 'age', 18, 65],
        ]);

        $conditions = $spec->getConditions();
        Assert::count($conditions, 1);
        Assert::same($conditions[0], ['not between', 'age', 18, 65]);
    }

    public function fromArrayWithIntegerFirstElementNotRecognized(): void
    {
        $spec = OrConditionSpecification::fromArray(conditions: [
            'col' => [0, 'val'],
        ]);

        $conditions = $spec->getConditions();
        Assert::count($conditions, 1);
        Assert::equals($conditions[0], ['col' => [0, 'val']]);
    }

    public function fromArrayWithBetweenWrongColumnInCanonical(): void
    {
        $spec = OrConditionSpecification::fromArray(conditions: [
            'age' => ['between', 'other_col', 1, 10],
        ]);

        $conditions = $spec->getConditions();
        Assert::count($conditions, 1);
        Assert::equals($conditions[0], ['age' => ['between', 'other_col', 1, 10]]);
    }

    public function fromArrayWithDefaultOperatorCanonicalWrongColumn(): void
    {
        $spec = OrConditionSpecification::fromArray(conditions: [
            'age' => ['>', 'other_col', 18],
        ]);

        $conditions = $spec->getConditions();
        Assert::count($conditions, 1);
        Assert::equals($conditions[0], ['age' => ['>', 'other_col', 18]]);
    }

    public function fromArrayWithNonArrayValueTreatedAsEquality(): void
    {
        $spec = OrConditionSpecification::fromArray(conditions: [
            'status' => 'active',
            'count' => 5,
        ]);

        $conditions = $spec->getConditions();
        Assert::count($conditions, 2);
        Assert::equals($conditions[0], ['status' => 'active']);
        Assert::equals($conditions[1], ['count' => 5]);
    }
}
