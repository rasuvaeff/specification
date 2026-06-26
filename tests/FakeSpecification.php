<?php

declare(strict_types=1);

namespace Rasuvaeff\Specification\Tests;

use Rasuvaeff\Specification\Specification;
use Rasuvaeff\Specification\SpecificationVisitor;

/**
 * @internal
 * @implements Specification<mixed>
 */
final class FakeSpecification implements Specification
{
    #[\Override]
    public function accept(SpecificationVisitor $visitor): mixed
    {
        return null;
    }
}
