# AGENTS.md — specification

Guidance for AI agents working on this package. Read before changing code.

## What this is

A Specification-pattern toolkit (PHP 8.3+) that composes query conditions and
applies them to a `yiisoft/db` `QueryInterface` via a visitor. Specs are
immutable; `QueryApplier::apply($spec, $query)` mutates the query in place.

Public API in `src/` (`Rasuvaeff\Specification\`): `SpecificationBuilder`,
`QueryApplier`, `CompositeSpecification`, `ComparisonSpecification`,
`OrSpecification`, `NotSpecification`, `RawSpecification`, `OrderBySpecification`,
`LimitSpecification`, `OrConditionSpecification`, and the `Specification<T>` /
`SpecificationVisitor<T>` interfaces (+ `QueryBuildingVisitor`).

## Golden rules

1. **Verification is mandatory.** Never claim "done" without a fresh green
   `composer build`. It runs psalm (errorLevel=1), cs, require-checker, and
   phpunit — including `tests/Integration/SqliteIntegrationTest`, which builds and
   executes real SQL on in-memory SQLite. "Should work" does not count.
2. **No suppressions.** No `@psalm-suppress`, no baseline. Fix the root cause.
3. **Values vs identifiers.** Comparison/IN/BETWEEN/LIKE values are bound as
   parameters by `yiisoft/db` (safe). Column names are NOT validated — they are
   passed to `yiisoft/db` and must be TRUSTED (typically hard-coded). Never build
   columns or `RawSpecification` conditions from untrusted input.
4. **Preserve the public contract.** Update README + tests with any API change.

## Commands

No PHP/Composer on the host — run in Docker via the `composer:2` image. From the
package root:

```bash
docker run --rm -v "$PWD":/app -w /app composer:2 composer build     # full gate
docker run --rm -v "$PWD":/app -w /app composer:2 composer cs:fix    # auto-fix style
docker run --rm -v "$PWD":/app -w /app composer:2 composer psalm
docker run --rm -v "$PWD":/app -w /app composer:2 composer test

# after changing composer.json (deps/metadata): refresh lock + re-normalize
docker run --rm -v "$PWD":/app -w /app composer:2 sh -c \
  'git config --global --add safe.directory /app; composer update -q; composer normalize'
```

Integration tests need no external DB — they use in-memory SQLite (the
`ext-pdo_sqlite` + `yiisoft/db-sqlite` dev deps), so `composer build` covers them.
`composer.lock` is gitignored (library).

## Invariants & gotchas

- **Parameter isolation.** OR/NOT build subqueries (`QueryBuildingVisitor`) and
  merge params; placeholders must not collide. This is exactly what
  `SqliteIntegrationTest` guards — extend it for any change to OR/NOT/subquery
  handling, and run it.
- `CompositeSpecification` composes with **AND**. For OR use `OrSpecification` or
  `SpecificationBuilder::orWhere()`.
- `ComparisonSpecification` validates operators (whitelist) and value↔operator
  consistency (NULL only with `=,!=,<>,is,is not`; array ops require arrays;
  `between` requires exactly two; LIKE ops require strings).
- `ilike`/`not ilike` are PostgreSQL-specific.
- Code: `declare(strict_types=1)`, `final` (most specs `final readonly`),
  `#[\Override]` on visitor/interface implementations, explicit types. Comments
  in English.

## When you finish

- Update `README.md` (and `examples/` if usage changed); update `CHANGELOG.md`
  when releasing.
- Re-run `composer build` and paste the output.
