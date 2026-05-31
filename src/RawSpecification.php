<?php

declare(strict_types=1);

namespace Rasuvaeff\Specification;

/**
 * Escape hatch for a raw query condition passed straight to `andWhere()`.
 *
 * SECURITY: `$condition` is NOT escaped. Never build it from untrusted input —
 * pass user values only through `$params` (bound placeholders), e.g.
 * `new RawSpecification('status = :s', ['s' => $userValue])`.
 *
 * @implements Specification<mixed>
 * @api
 */
final readonly class RawSpecification implements Specification
{
    /**
     * @param string|array<mixed> $condition Trusted condition (string or yiisoft/db condition array).
     * @param array<string, mixed> $params Bound parameters for placeholders in $condition.
     */
    public function __construct(
        private string|array $condition,
        private array $params = [],
    ) {}

    /**
     * @template TVisitorReturn
     * @param SpecificationVisitor<TVisitorReturn> $visitor
     * @return TVisitorReturn
     */
    #[\Override]
    public function accept(SpecificationVisitor $visitor)
    {
        return $visitor->visitRaw($this);
    }

    public function getCondition(): string|array
    {
        return $this->condition;
    }

    public function getParams(): array
    {
        return $this->params;
    }
}
