<?php

declare(strict_types=1);

namespace Rasuvaeff\Specification\Tests;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Rasuvaeff\Specification\RawSpecification;
use Rasuvaeff\Specification\SpecificationVisitor;

#[CoversClass(RawSpecification::class)]
final class RawSpecificationTest extends TestCase
{
    #[Test]
    public function getCondition(): void
    {
        $condition = 'status = :status';
        $params = [':status' => 'active'];

        $spec = new RawSpecification(condition: $condition, params: $params);

        $this->assertSame($condition, $spec->getCondition());
        $this->assertSame($params, $spec->getParams());
    }

    #[Test]
    public function getConditionWithArray(): void
    {
        $condition = ['or', ['a' => 1], ['b' => 2]];

        $spec = new RawSpecification(condition: $condition);

        $this->assertSame($condition, $spec->getCondition());
        $this->assertEmpty($spec->getParams());
    }

    #[Test]
    public function acceptReturnsVisitorResult(): void
    {
        $spec = new RawSpecification(condition: 'x = 1');

        /** @var MockObject&SpecificationVisitor<string> $visitor */
        $visitor = $this->createMock(SpecificationVisitor::class);
        $visitor->expects($this->once())
            ->method('visitRaw')
            ->with($spec)
            ->willReturn('ok');

        $result = $spec->accept(visitor: $visitor);
        $this->assertSame('ok', $result);
    }
}
