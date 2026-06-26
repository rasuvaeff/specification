<?php

declare(strict_types=1);

namespace Rasuvaeff\Specification\Benchmarks;

use Rasuvaeff\Specification\CompositeSpecification;
use Rasuvaeff\Specification\OrSpecification;
use Rasuvaeff\Specification\SpecificationBuilder;
use Testo\Bench;

/**
 * Compares orWhere() (callback-based builder API) against
 * manually constructing the equivalent OrSpecification tree.
 *
 * orWhere() allocates a temporary mutable builder per call and invokes
 * a closure; direct construction avoids both. Both produce the same
 * CompositeSpecification([OrSpecification([...])]) structure.
 */
final class OrCompositionBench
{
    #[Bench(
        callables: [
            'direct' => [self::class, 'buildDirect'],
        ],
        calls: 1_000,
        iterations: 10,
    )]
    public static function buildViaOrWhere(): CompositeSpecification
    {
        return SpecificationBuilder::create()
            ->whereEqual('status', 'active')
            ->orWhere(fn($b) => $b->whereEqual('status', 'pending'))
            ->orWhere(fn($b) => $b->whereEqual('status', 'trial'))
            ->build();
    }

    public static function buildDirect(): CompositeSpecification
    {
        return CompositeSpecification::create()
            ->withSpecification(specification: OrSpecification::create(
                CompositeSpecification::create()
                    ->withComparison(column: 'status', value: 'active', operator: '='),
                CompositeSpecification::create()
                    ->withComparison(column: 'status', value: 'pending', operator: '='),
                CompositeSpecification::create()
                    ->withComparison(column: 'status', value: 'trial', operator: '='),
            ));
    }
}
