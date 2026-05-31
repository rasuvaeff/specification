<?php

declare(strict_types=1);

namespace Rasuvaeff\Specification;

/**
 * Visitor for specification types. Each method returns the template type T,
 * allowing different visitors to produce different result types.
 *
 * @template T
 * @api
 */
interface SpecificationVisitor
{
    /**
     * @return T
     */
    public function visitComparison(ComparisonSpecification $specification);

    /**
     * @return T
     */
    public function visitComposite(CompositeSpecification $specification);

    /**
     * @return T
     */
    public function visitNot(NotSpecification $specification);

    /**
     * @return T
     */
    public function visitOr(OrSpecification $specification);

    /**
     * @return T
     */
    public function visitOrCondition(OrConditionSpecification $specification);

    /**
     * @return T
     */
    public function visitRaw(RawSpecification $specification);

    /**
     * @return T
     */
    public function visitOrderBy(OrderBySpecification $specification);

    /**
     * @return T
     */
    public function visitLimit(LimitSpecification $specification);

    /**
     * @return T
     */
    public function visitOffset(OffsetSpecification $specification);
}
