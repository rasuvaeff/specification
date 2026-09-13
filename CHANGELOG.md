# Changelog

## Unreleased

### Fixed

- Renaming a colliding raw placeholder also rewrote every sibling it was a
  prefix of: `:sort` → `:sort_0` turned `:sort_max` into `:sort_0_max`, which
  nothing bound, and the query failed with `column index out of range`. The
  rename now replaces whole `:name` tokens only, in one pass (#30).
- Two `RawSpecification`s reusing one placeholder name under a single
  `CompositeSpecification` overwrote each other's value, so `a AND b` selected
  a different row set than `b AND a`. `visitRaw()` now routes its params
  through the same remap as NOT/OR: the second leaf binds `:name_0`. As a
  consequence `getParams()` keys of raw leaves are always colon-prefixed
  (`['age' => 18]` is bound as `':age'`); positional `?` params keep their
  integer keys instead of being turned into a `:0` name (#31).

## 1.2.0 — 2026-09-12

### Fixed

- `startsWith()`, `endsWith()` and `contains()` searched for a literal `%`:
  yiisoft/db ≥ 2.0 escapes the LIKE value and wraps it in `%…%` unless told
  otherwise, so the wildcard the factory added was escaped. The three helpers
  now hand a clean string to the builder with `mode: LikeMode::StartsWith`
  / `EndsWith` / `Contains`, and `like()` / `notLike()` send the pattern
  verbatim (`mode: Custom`, `escape: false`) — also for a `['like', col,
  pattern]` entry of `withOrCondition()`. `ilike` / `not ilike` no longer
  render a verbatim `ILIKE` (a syntax error outside PostgreSQL): they are
  `LIKE` with `caseSensitive: false`. New `LikeMatch` enum,
  `ComparisonSpecification::getLikeMatch()` and an optional `$likeMatch`
  argument on the constructor, `CompositeSpecification::withComparison()`
  and `SpecificationBuilder::where()`. A custom visitor that reads
  `getValue()` of a `startsWith()` / `endsWith()` / `contains()` specification
  now gets the bare string and must honour `getLikeMatch()` (#27).

### Changed

- Add a `zizmor` GitHub Actions security audit workflow and skip heavy CI jobs on irrelevant changes via a path-aware gate.

## 1.1.0 — 2026-07-25

- Ship an AI agent skill (`resources/skills/rasuvaeff-specification/SKILL.md`
  + `extra.skills` in composer.json): projects using the `llm/skills` Composer
  plugin get the skill synced into `.agents/skills/` automatically on install.
- Bump dev dependency `rasuvaeff/property-testing` to `^2.6`.

## 1.0.2 — 2026-06-30

- Add `/benchmarks` and `/Makefile` to `.gitattributes` export-ignore.

## 1.0.1 — 2026-06-26

- Migrate tests from PHPUnit to Testo (testo/testo + testo/bridge-infection + testo/bench).

## 1.0.0 — 2026-05-30

Initial release.
