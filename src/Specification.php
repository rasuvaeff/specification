<?php

declare(strict_types=1);

namespace Rasuvaeff\Specification;

/**
 * @template T
 * @api
 */
interface Specification
{
    /**
     * @param SpecificationVisitor<T> $visitor
     * @return T
     */
    public function accept(SpecificationVisitor $visitor);
}
