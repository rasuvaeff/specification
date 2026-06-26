<?php

declare(strict_types=1);

namespace Rasuvaeff\Specification\Benchmarks;

use Rasuvaeff\Specification\CompositeSpecification;
use Rasuvaeff\Specification\SpecificationBuilder;
use Testo\Bench;

/**
 * Compares immutable SpecificationBuilder (clones on every step)
 * against direct CompositeSpecification composition (no builder overhead).
 */
final class SpecificationBuilderBench
{
    #[Bench(
        callables: [
            'direct' => [self::class, 'buildDirect'],
        ],
        calls: 1_000,
        iterations: 10,
    )]
    public static function buildViaBuilder(): CompositeSpecification
    {
        return SpecificationBuilder::create()
            ->whereEqual('status', 'active')
            ->whereGreaterThan('age', 18)
            ->whereIn('role', ['admin', 'editor', 'viewer'])
            ->whereLike('email', '%@example.com')
            ->whereNotNull('verified_at')
            ->limit(100)
            ->offset(0)
            ->build();
    }

    public static function buildDirect(): CompositeSpecification
    {
        return CompositeSpecification::create()
            ->withComparison(column: 'status', value: 'active', operator: '=')
            ->withComparison(column: 'age', value: 18, operator: '>')
            ->withComparison(column: 'role', value: ['admin', 'editor', 'viewer'], operator: 'in')
            ->withComparison(column: 'email', value: '%@example.com', operator: 'like')
            ->withComparison(column: 'verified_at', value: null, operator: 'is not')
            ->withLimit(limit: 100)
            ->withOffset(offset: 0);
    }
}
