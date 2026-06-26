<?php

declare(strict_types=1);

namespace Rasuvaeff\Specification\Tests;

use Rasuvaeff\Specification\ComparisonSpecification;
use Rasuvaeff\Specification\CompositeSpecification;
use Rasuvaeff\Specification\LimitSpecification;
use Rasuvaeff\Specification\NotSpecification;
use Rasuvaeff\Specification\OffsetSpecification;
use Rasuvaeff\Specification\OrConditionSpecification;
use Rasuvaeff\Specification\OrderBySpecification;
use Rasuvaeff\Specification\OrSpecification;
use Rasuvaeff\Specification\RawSpecification;
use Rasuvaeff\Specification\SpecificationVisitor;

/**
 * @internal
 * @implements SpecificationVisitor<mixed>
 */
final class FakeVisitor implements SpecificationVisitor
{
    public string $lastMethod = '';
    public mixed $lastArg = null;

    public function __construct(public readonly mixed $returnValue = null) {}

    #[\Override]
    public function visitComparison(ComparisonSpecification $specification): mixed
    {
        $this->lastMethod = 'visitComparison';
        $this->lastArg = $specification;

        return $this->returnValue;
    }

    #[\Override]
    public function visitComposite(CompositeSpecification $specification): mixed
    {
        $this->lastMethod = 'visitComposite';
        $this->lastArg = $specification;

        return $this->returnValue;
    }

    #[\Override]
    public function visitNot(NotSpecification $specification): mixed
    {
        $this->lastMethod = 'visitNot';
        $this->lastArg = $specification;

        return $this->returnValue;
    }

    #[\Override]
    public function visitOr(OrSpecification $specification): mixed
    {
        $this->lastMethod = 'visitOr';
        $this->lastArg = $specification;

        return $this->returnValue;
    }

    #[\Override]
    public function visitOrCondition(OrConditionSpecification $specification): mixed
    {
        $this->lastMethod = 'visitOrCondition';
        $this->lastArg = $specification;

        return $this->returnValue;
    }

    #[\Override]
    public function visitRaw(RawSpecification $specification): mixed
    {
        $this->lastMethod = 'visitRaw';
        $this->lastArg = $specification;

        return $this->returnValue;
    }

    #[\Override]
    public function visitOrderBy(OrderBySpecification $specification): mixed
    {
        $this->lastMethod = 'visitOrderBy';
        $this->lastArg = $specification;

        return $this->returnValue;
    }

    #[\Override]
    public function visitLimit(LimitSpecification $specification): mixed
    {
        $this->lastMethod = 'visitLimit';
        $this->lastArg = $specification;

        return $this->returnValue;
    }

    #[\Override]
    public function visitOffset(OffsetSpecification $specification): mixed
    {
        $this->lastMethod = 'visitOffset';
        $this->lastArg = $specification;

        return $this->returnValue;
    }
}
