<?php

declare(strict_types=1);

namespace Rasuvaeff\Specification\Tests;

use Rasuvaeff\Specification\RawSpecification;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Test;

#[Test]
#[Covers(RawSpecification::class)]
final class RawSpecificationTest
{
    public function getCondition(): void
    {
        $condition = 'status = :status';
        $params = [':status' => 'active'];

        $spec = new RawSpecification(condition: $condition, params: $params);

        Assert::same($spec->getCondition(), $condition);
        Assert::same($spec->getParams(), $params);
    }

    public function getConditionWithArray(): void
    {
        $condition = ['or', ['a' => 1], ['b' => 2]];

        $spec = new RawSpecification(condition: $condition);

        Assert::same($spec->getCondition(), $condition);
        Assert::blank($spec->getParams());
    }

    public function acceptReturnsVisitorResult(): void
    {
        $spec = new RawSpecification(condition: 'x = 1');
        $visitor = new FakeVisitor(returnValue: 'ok');

        $result = $spec->accept(visitor: $visitor);

        Assert::same($visitor->lastMethod, 'visitRaw');
        Assert::same($visitor->lastArg, $spec);
        Assert::same($result, 'ok');
    }
}
